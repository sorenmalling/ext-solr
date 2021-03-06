<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\Helper\FakeObjectManager;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class QueryGroupFacetParserTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
abstract class AbstractFacetParserTest extends UnitTest
{
    /**
     * @param string $fixtureFile
     * @param array $facetConfiguration
     * @param array $activeFilters
     * @return SearchResultSet
     */
    protected function initializeSearchResultSetFromFakeResponse($fixtureFile, $facetConfiguration, array $activeFilters = [])
    {
        $fakeResponseJson = $this->getFixtureContentByName($fixtureFile);

        $facetingMock = $this->getMockBuilder(Faceting::class)->setMethods(['getSorting'])->disableOriginalConstructor()->getMock();
        $facetingMock->expects($this->any())->method('getSorting')->will($this->returnValue(''));
        $usedQueryMock = $this->getMockBuilder(Query::class)->setMethods(['getFaceting'])->disableOriginalConstructor()->getMock();
        $usedQueryMock->expects($this->any())->method('getFaceting')->will($this->returnValue($facetingMock));

        $searchRequestMock = $this->getMockBuilder(SearchRequest::class)
            ->setMethods(['getActiveFacetNames', 'getContextTypoScriptConfiguration', 'getActiveFacets', 'getActiveFacetValuesByName'])
            ->getMock();

        $fakeResponse = new ResponseAdapter($fakeResponseJson, 200);

        $searchResultSet = new SearchResultSet();
        $searchResultSet->setUsedQuery($usedQueryMock);
        $searchResultSet->setUsedSearchRequest($searchRequestMock);
        $searchResultSet->setResponse($fakeResponse);

        $activeUrlFacets = new UrlFacetContainer(
            new ArrayAccessor([ 'tx_solr' => ['filter' => $activeFilters] ])
        );
        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = $facetConfiguration;
        $typoScriptConfiguration = new TypoScriptConfiguration($configuration);
        $searchRequestMock->expects($this->any())
            ->method('getContextTypoScriptConfiguration')
            ->will($this->returnValue($typoScriptConfiguration));

        // Replace calls with own data bag
        $searchRequestMock->expects($this->any())
            ->method('getActiveFacetNames')
            ->will($this->returnCallback(function() use ($activeUrlFacets) {
                return $activeUrlFacets->getActiveFacetNames();
            }));
        $searchRequestMock->expects($this->any())
            ->method('getActiveFacets')
            ->will($this->returnCallback(function() use ($activeUrlFacets) {
                return $activeUrlFacets->getActiveFacets();
            }));
        $searchRequestMock->expects($this->any())
            ->method('getActiveFacetValuesByName')
            ->will($this->returnCallback(function(string $facetName) use ($activeUrlFacets) {
                return $activeUrlFacets->getActiveFacetValuesByName($facetName);
            }));

        return $searchResultSet;
    }

    /**
     * @param string $className
     * @return AbstractFacetParser
     */
    protected function getInitializedParser($className)
    {
        $parser = GeneralUtility::makeInstance($className);
        // @extensionScannerIgnoreLine

        $fakeObjectManager = new FakeObjectManager();

        $parser->injectObjectManager($fakeObjectManager);

        return $parser;
    }
}
