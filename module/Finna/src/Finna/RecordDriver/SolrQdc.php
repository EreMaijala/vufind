<?php
/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2013-2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Anna Pienimäki <anna.pienimaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Anna Pienimäki <anna.pienimaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrQdc extends \VuFind\RecordDriver\SolrDefault
    implements \Laminas\Log\LoggerAwareInterface
{
    use Feature\SolrFinnaTrait;
    use Feature\FinnaXmlReaderTrait;
    use Feature\FinnaUrlCheckTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Image size mappings
     *
     * @var array
     */
    protected $imageSizeMappings = [
        'THUMBNAIL' => 'small',
        'square' => 'small',
        'small' => 'small',
        'medium' => 'medium',
        'large' => 'large',
        'original' => 'original'
    ];

    /**
     * Image mime types
     *
     * @var array
     */
    protected $imageMimeTypes = [
        'image/jpeg',
        'image/png'
    ];

    /**
     * Mappings for series information, type => key
     *
     * @var array
     */
    protected $seriesInfoMappings = [
        'ispartofseries' => 'name',
        'numberinseries' => 'partNumber'
    ];

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->searchSettings = $searchSettings;
    }

    /**
     * Return an associative array of abstracts associated with this record
     *
     * @return array of abstracts using abstract languages as keys
     */
    public function getAbstracts()
    {
        $abstractValues = [];
        $abstracts = [];
        $abstract = '';
        $lang = '';
        $xml = $this->getXmlRecord();
        foreach ($xml->abstract ?? [] as $node) {
            $abstract = (string)$node;
            $lang = (string)$node['lang'];
            if ($lang == 'en') {
                $lang = 'en-gb';
            }
            $abstracts[$lang] = $abstract;
        }

        return $abstracts;
    }

    /**
     * Get descriptions as an array
     *
     * @return array
     */
    public function getDescriptions(): array
    {
        $xml = $this->getXmlRecord();
        $locale = $this->getLocale();
        $all = [];
        $primary = [];
        foreach ($xml->description ?? [] as $description) {
            $lang = (string)$description['lang'];
            $trimmed = trim((string)$description);
            if ($lang === $locale) {
                $primary[] = $trimmed;
            }
            $all[] = $trimmed;
        }
        return $primary ?: $all;
    }

    /**
     * Get an array of types for the record.
     *
     * @return array
     */
    public function getTypes(): array
    {
        $xml = $this->getXmlRecord();
        $results = [];
        foreach ($xml->type ?? [] as $type) {
            $results[] = (string)$type;
        }
        return $results;
    }

    /**
     * Get an array of mediums for the record
     *
     * @return array
     */
    public function getPhysicalMediums(): array
    {
        $xml = $this->getXmlRecord();
        $results = [];
        foreach ($xml->medium as $medium) {
            $results[] = trim((string)$medium);
        }
        return $results;
    }

    /**
     * Get an array of formats/extents for the record
     *
     * @return array
     */
    public function getPhysicalDescriptions(): array
    {
        $xml = $this->getXmlRecord();
        $results = [];
        foreach ([$xml->format, $xml->extent] as $nodes) {
            foreach ($nodes as $node) {
                $results[] = trim((string)$node);
            }
        }
        return $results;
    }

    /**
     * Return an array of image URLs associated with this record with keys:
     * - url         Image URL
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return mixed
     */
    public function getAllImages($language = 'fi', $includePdf = true)
    {
        $cacheKey = __FUNCTION__ . "/$language" . $includePdf ? '/1' : '/0';
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $results = [];
        $rights = [];
        $pdf = false;
        $xml = $this->getXmlRecord();
        $thumbnails = [];
        $otherSizes = [];

        $rightsStmt = $this->getMappedRights((string)($xml->rights ?? ''));
        $rights = [
            'copyright' => $rightsStmt,
            'link' => $this->getRightsLink($rightsStmt, $language)
        ];

        $addToResults = function ($imageData) use (&$results) {
            if (!isset($imageData['urls']['small'])) {
                $imageData['urls']['small'] = $imageData['urls']['medium']
                    ?? $imageData['urls']['large'];
            }
            if (!isset($imageData['urls']['medium'])) {
                $imageData['urls']['medium'] = $imageData['urls']['small'];
            }
            $results[] = $imageData;
        };

        foreach ($xml->file as $node) {
            $attributes = $node->attributes();
            $type = $attributes->type ?? '';
            if (!empty($attributes->type)
                && !in_array($type, $this->imageMimeTypes)
            ) {
                continue;
            }
            $url = (string)($attributes->href ?? $node);
            if (!preg_match('/\.(jpg|png)$/i', $url)
                || !$this->isUrlLoadable($url, $this->getUniqueID())
            ) {
                continue;
            }

            $bundle = (string)$attributes->bundle;
            if ($bundle === 'THUMBNAIL' && !$otherSizes) {
                // Lets see if the record contains only thumbnails
                $thumbnails[] = $url;
            } else {
                // QDC has no way of telling how to link
                // images so take only first in this situation
                $size = $this->imageSizeMappings[$bundle] ?? false;
                if ($size && !isset($otherSizes[$size])) {
                    $otherSizes[$size] = $url;
                }
            }
        }

        if ($thumbnails && !$otherSizes) {
            foreach ($thumbnails as $url) {
                $addToResults(
                    [
                        'urls' => ['large' => $url],
                        'description' => '',
                        'rights' => $rights
                    ]
                );
            }
        } elseif ($otherSizes) {
            $addToResults(
                [
                    'urls' => $otherSizes,
                    'description' => '',
                    'rights' => $rights
                ]
            );
        }

        // Attempt to find a PDF file to be converted to a coverimage
        if ($includePdf && empty($results)) {
            $urls = [];
            foreach ($xml->file as $node) {
                $attributes = $node->attributes();
                if ((string)$attributes->bundle !== 'ORIGINAL') {
                    continue;
                }
                $mimes = ['application/pdf'];
                if (isset($attributes->type)) {
                    if (!in_array($attributes->type, $mimes)) {
                        continue;
                    }
                }
                $url = isset($attributes->href)
                    ? (string)$attributes->href : (string)$node;

                if (!preg_match('/\.(pdf)$/i', $url)) {
                    continue;
                }
                $urls['small'] = $urls['large'] = $url;
                $addToResults(
                    [
                        'urls' => $urls,
                        'description' => '',
                        'rights' => $rights,
                        'pdf' => true
                    ]
                );
                break;
            }
        }
        return $this->cache[$cacheKey] = $results;
    }

    /**
     * Return an external URL where a displayable description text
     * can be retrieved from, if available; false otherwise.
     *
     * @return mixed
     */
    public function getDescriptionURL()
    {
        if ($isbn = $this->getCleanISBN()) {
            return 'http://s1.doria.fi/getText.php?query=' . $isbn;
        }
        return false;
    }

    /**
     * Return education programs
     *
     * @return array
     */
    public function getEducationPrograms()
    {
        $result = [];
        foreach ($this->getXmlRecord()->programme as $programme) {
            $result[] = (string)$programme;
        }
        return $result;
    }

    /**
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        $record = clone $this->getXmlRecord();
        while ($record->abstract) {
            unset($record->abstract[0]);
        }
        // Try to filter out any summary or abstract fields
        $filterTerms = [
            'tiivistelmä', 'abstract', 'abstracts', 'abstrakt', 'sammandrag',
            'sommario', 'summary', 'аннотация'
        ];
        for ($i = count($record->description) - 1; $i >= 0; $i--) {
            $node = $record->description[$i];
            $description = mb_strtolower((string)$node, 'UTF-8');
            $firstWords = array_slice(preg_split('/\s/', $description), 0, 5);
            if (array_intersect($firstWords, $filterTerms)) {
                unset($record->description[$i]);
            }
        }
        return $record->asXML();
    }

    /**
     * Get identifiers as an array
     *
     * @return array
     */
    public function getOtherIdentifiers(): array
    {
        $results = [];
        $xml = $this->getXmlRecord();
        foreach ([$xml->identifier, $xml->isFormatOf] as $field) {
            foreach ($field as $identifier) {
                $type = (string)$identifier['type'];
                if (in_array($type, ['issn', 'isbn'])) {
                    continue;
                }
                $trimmed = str_replace('-', '', trim($identifier));
                // ISBN
                if (preg_match('{^[0-9]{9,12}[0-9xX]}', $trimmed)) {
                    continue;
                }
                $trimmed = trim($identifier);
                // ISSN
                if (preg_match('{(issn:)[\S]{4}\-[\S]{4}}', $trimmed)) {
                    continue;
                }

                // Leave out some obvious matches like urls or urns
                if (!preg_match('{(^urn:|^https?)}i', $trimmed)) {
                    $detail = (string)$identifier['type'];
                    $data = $identifier;
                    $results[] = compact('data', 'detail');
                }
            }
        }
        return $results;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        $result = [];
        $xml = $this->getXmlRecord();
        foreach ([$xml->identifier, $xml->isFormatOf] as $field) {
            foreach ($field as $identifier) {
                $trimmed = str_replace('-', '', trim($identifier));
                if ((string)$identifier['type'] === 'isbn'
                    || preg_match('{^[0-9]{9,12}[0-9xX]}', $trimmed)
                ) {
                    $result[] = $identifier;
                }
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * Return keywords
     *
     * @return array
     */
    public function getKeywords()
    {
        $result = [];
        foreach ($this->getXmlRecord()->keyword as $keyword) {
            $result[] = (string)$keyword;
        }
        return $result;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  The exact nature of the data may
     * vary depending on the data source -- the important thing is that the
     * Record Driver + Search Results objects work together correctly.
     *
     * @return void
     */
    public function setRawData($data)
    {
        parent::setRawData($data);
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $urls = [];
        foreach (parent::getURLs() as $url) {
            if (!$this->urlBlocked($url['url'] ?? '')) {
                $urls[] = $url;
            }
        }
        $urls = $this->resolveUrlTypes($urls);
        return $urls;
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        if ('oai_qdc' === $format) {
            return $this->fields['fullrecord'];
        }
        return parent::getXML($format, $baseUrl, $recordLink);
    }

    /**
     * Get series information
     *
     * @return array
     */
    public function getSeries(): array
    {
        $locale = $this->getLocale();
        $xml = $this->getXmlRecord();
        $results = [];
        foreach ($xml->relation ?? [] as $relation) {
            $type = (string)$relation->attributes()->{'type'};
            $lang = (string)$relation->attributes()->{'lang'} ?: 'nolocale';
            $trimmed = trim((string)$relation);

            if ($key = $this->seriesInfoMappings[$type] ?? false) {
                if (empty($results[$lang][$key])) {
                    $results[$lang][$key] = $trimmed;
                }
            }
        }

        return isset($results[$locale])
            ? [$results[$locale]]
            : array_values($results);
    }
}