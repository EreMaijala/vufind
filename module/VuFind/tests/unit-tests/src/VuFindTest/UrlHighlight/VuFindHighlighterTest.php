<?php

/**
 * VuFindHighlighter Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\UrlHighlight;

use VStelmakh\UrlHighlight\Replacer\ReplacerFactory;
use VuFind\UrlHighlight\VuFindHighlighter;
use VuFind\View\Helper\Root\ProxyUrl;

/**
 * VuFindHighlighter Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VuFindHighlighterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the highlight method.
     *
     * @param string $url      URL
     * @param string $expected Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getHighlightDataProvider')]
    public function testGetHighlight(string $url, string $expected): void
    {
        $proxyUrl = $this->createMock(ProxyUrl::class);
        $proxyUrl
            ->expects($this->atLeastOnce())
            ->method('__invoke')
            ->willReturn('URL_WITH_PROXY');
        $vuFindHighlighter = new VuFindHighlighter($proxyUrl);

        $replacer = ReplacerFactory::createReplacer();
        $actual = $vuFindHighlighter->highlight($url, $replacer);
        $this->assertSame($expected, $actual);
    }

    /**
     * Data provider for testGetHighlight().
     *
     * @return \Iterator
     */
    public static function getHighlightDataProvider(): \Iterator
    {
        yield 'http' => [
            'https://vufind.org',
            '<a href="URL_WITH_PROXY">https://vufind.org</a>',
        ];
        yield 'complex link' => [
            'https://vufind.org?foo=1&bar=2#xyzzy',
            '<a href="URL_WITH_PROXY">https://vufind.org?foo=1&bar=2#xyzzy</a>',
        ];
        yield 'quotes' => [
            'https://vufind.org/path/with"quotes"/?q=search',
            '<a href="URL_WITH_PROXY">https://vufind.org/path/with"quotes"/?q=search</a>',
        ];
        yield 'no scheme' => [
            'vufind.org',
            '<a href="URL_WITH_PROXY">vufind.org</a>',
        ];
        yield 'email' => [
            'user@vufind.org',
            '<a href="URL_WITH_PROXY">user@vufind.org</a>',
        ];
    }
}
