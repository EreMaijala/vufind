<?php
namespace VNF\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array (
             'recorddriver' => array (
                 'factories' => array(
                     'solrvnf_mkp' => 'VNF\RecordDriver\Factory::getSolrMkp',
                     'solrvnf_mzk' => 'VNF\RecordDriver\Factory::getSolrMarc',
                     'solrvnf_nkp' => 'VNF\RecordDriver\Factory::getSolrMarc',
                     'solrvnf_cbvk'=> 'VNF\RecordDriver\Factory::getSolrMarc',
                     'solrvnf_vkol'=> 'VNF\RecordDriver\Factory::getSolrMarc',
                     'solrvnf_ktn' => 'VNF\RecordDriver\Factory::getSolrKtn',
                     'solrvnf_kkfb' => 'VNF\RecordDriver\Factory::getSolrKkfb',
                     'solrvnf_svkk' => 'VNF\RecordDriver\Factory::getSolrMarc',
                     'solrvnf_kjm' => 'VNF\RecordDriver\Factory::getSolrKjm',
                     'solrvnf_mkpr' => 'VNF\RecordDriver\Factory::getSolrMkpr',
                     'solrmerged'  => 'VNF\RecordDriver\Factory::getSolrMarcMerged',
                     'solrdefault' => 'VNF\RecordDriver\Factory::getSolrMarc',
                     'solrvnf_sup' => 'VNF\RecordDriver\Factory::getSolrSup',
                      ), /* factories */
                 ), /* recorddriver */
                 'recordtab' => array(
                     'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                         'invokables' => array(
                             'supraphondescriptiontab' => 'VNF\RecordTab\SupraphonDescriptionTab',
                             //'supraphonrecordtab' => 'VNF\RecordTab\SupraphonRecordTab',
                             'libraries' => 'VNF\RecordTab\Libraries'
                         ),
                 ), /* recordtab */
             ), /* plugin_managers */
        'recorddriver_tabs' => array(
             'VNF\RecordDriver\SolrMarc' => array(
                    'tabs' => array (
                        'TOC' => 'VNF\RecordTab\TOC',
                    ),
                    'defaultTab' => 'TOC',
             ),
             'VNF\RecordDriver\SolrSup' => array(
                 'tabs' => array (
                    'TOC' => 'VNF\RecordTab\TOC',
                 ),
                 'defaultTab' => 'SupraphonRecordTab',
            ),
            'VNF\RecordDriver\SolrMarcMerged' => array(
                'tabs' => array (
                   'TOC' => 'VNF\RecordTab\TOC',
                ),
                'defaultTab' => 'TOC',
            ),
        ),
    ),
);

return $config;
