<?php
/**
 * This file is part of PDepend.
 * 
 * PHP Version 5
 *
 * Copyright (c) 2008-2013, Manuel Pichler <mapi@pdepend.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @copyright 2008-2013 Manuel Pichler. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

namespace PDepend\Metrics\Analyzer;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use PDepend\Metrics\AbstractMetricsTest;
use PDepend\Source\AST\ASTArtifactList;
use PDepend\Source\AST\ASTNamespace;
use PDepend\Util\Cache\Driver\MemoryCacheDriver;

/**
 * Test case for the Halstead cyclomatic analyzer.
 *
 * @copyright 2008-2014 Dmitry Balabka. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @covers \PDepend\Metrics\AbstractCachingAnalyzer
 * @covers \PDepend\Metrics\Analyzer\MaintainabilityIndexAnalyzer
 * @group unittest
 */
class MaintainabilityIndexAnalyzerTest extends AbstractMetricsTest
{
    /**
     * @var \PDepend\Util\Cache\CacheDriver
     * @since 1.0.0
     */
    private $cache;

    /**
     * Initializes a in memory cache.
     *
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();

        $this->cache = new MemoryCacheDriver();
    }

    /**
     * Tests that the {@link \PDepend\Metrics\Analyzer\ClassLevelAnalyzer::analyzer()}
     * method fails with an exception if no cc analyzer was set.
     *
     * @return void
     * @expectedException \RuntimeException
     */
    public function testAnalyzerFailsWithoutCCAnalyzerFail()
    {
        $namespace = new ASTNamespace('package1');
        $namespaces = new ASTArtifactList(array($namespace));

        $analyzer = new ClassLevelAnalyzer();
        $analyzer->analyze($namespaces);
    }

    /**
     * Tests that {@link \PDepend\Metrics\Analyzer\ClassLevelAnalyzer::addAnalyzer()}
     * fails for an invalid child analyzer.
     *
     * @return void
     * @expectedException InvalidArgumentException
     */
    public function testAddAnalyzerFailsForAnInvalidAnalyzerTypeFail()
    {
        $analyzer = new ClassLevelAnalyzer();
        $analyzer->addAnalyzer(new CodeRankAnalyzer());
    }

    /**
     * testGetRequiredAnalyzersReturnsExpectedClassNames
     *
     * @return void
     */
    public function testGetRequiredAnalyzersReturnsExpectedClassNames()
    {
        $analyzer = new ClassLevelAnalyzer();
        $this->assertEquals(
            array('PDepend\\Metrics\\Analyzer\\CyclomaticComplexityAnalyzer'),
            $analyzer->getRequiredAnalyzers()
        );
    }

    /**
     * testGetCCNReturnsZeroForUnknownNode
     *
     * @return void
     */
    public function testGetNodeMetricsReturnsEmptyForUnknownNode()
    {
        $analyzer = $this->_createAnalyzer();
        $this->assertEquals(array(), $analyzer->getNodeMetrics($this->getMock('\\PDepend\\Source\\AST\\ASTArtifact')));
    }

    /**
     * Tests that the analyzer calculates the correct operators and operands counts.
     *
     * @return void
     */
    public function testCalculateMaintainabilityIndex()
    {
        $namespaces = $this->parseCodeResourceForTest();

        $analyzer = $this->_createAnalyzer();
        $analyzer->analyze($namespaces);

        $actual   = array();
        $expected = array(

        );

        foreach ($namespaces as $namespace) {
            $actual[$namespace->getName()] = $analyzer->getNodeMetrics($namespace);
        }
        foreach ($namespaces[0]->getFunctions() as $function) {
            $actual[$function->getName()] = $analyzer->getNodeMetrics($function);
        }
        foreach ($namespaces[0]->getClasses() as $class) {
            $className = $class->getName();
            foreach ($class->getAllMethods() as $method) {
                $actual[$className . '::' . $method->getName()] = $analyzer->getNodeMetrics($method);
            }
        }
        foreach ($namespaces[0]->getTraits() as $trait) {
            $traitName = $trait->getName();
            foreach ($trait->getAllMethods() as $method) {
                $actual[$traitName . '::' . $method->getName()] = $analyzer->getNodeMetrics($method);
            }
        }
        foreach ($namespaces[0]->getInterfaces() as $class) {
            $className = $class->getName();
            foreach ($class->getAllMethods() as $method) {
                $actual[$className . '::' . $method->getName()] = $analyzer->getNodeMetrics($method);
            }
        }
        ksort($expected);
        ksort($actual);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Returns a pre configured ccn analyzer.
     *
     * @return \PDepend\Metrics\Analyzer\MaintainabilityIndexAnalyzer
     * @since 1.0.0
     */
    private function _createAnalyzer()
    {
        $ccnAnalyzer = new CyclomaticComplexityAnalyzer();
        $ccnAnalyzer->setCache($this->cache);

        $hcAnalyzer = new HalsteadComplexityAnalyzer();
        $hcAnalyzer->setCache($this->cache);

        $nodeAnalyzer = new NodeCountAnalyzer();

        $locAnalyzer = new NodeLocAnalyzer();
        $locAnalyzer->setCache($this->cache);

        $analyzer = new MaintainabilityIndexAnalyzer();
        $analyzer->setCache($this->cache);
        $analyzer->addAnalyzer($ccnAnalyzer);
        $analyzer->addAnalyzer($hcAnalyzer);
        $analyzer->addAnalyzer($nodeAnalyzer);
        $analyzer->addAnalyzer($locAnalyzer);

        return $analyzer;
    }
}
