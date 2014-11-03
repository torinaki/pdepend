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

use PDepend\Metrics\AbstractMetricsTest;
use PDepend\Util\Cache\Driver\MemoryCacheDriver;

/**
 * Test case for the Halstead cyclomatic analyzer.
 *
 * @copyright 2008-2014 Dmitry Balabka. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @covers \PDepend\Metrics\AbstractCachingAnalyzer
 * @covers \PDepend\Metrics\Analyzer\HalsteadComplexityAnalyzer
 * @group unittest
 */
class HalsteadComplexityAnalyzerTest extends AbstractMetricsTest
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
     * testGetCCNReturnsZeroForUnknownNode
     *
     * @return void
     */
    public function testGetHCNReturnsZeroForUnknownNode()
    {
        $analyzer = $this->_createAnalyzer();
        $this->assertEquals(0, $analyzer->getHcn($this->getMock('\\PDepend\\Source\\AST\\ASTArtifact')));
    }

    /**
     * Tests that the analyzer calculates the correct function cc numbers.
     *
     * @return void
     */
    public function testCalculateFunctionHCN()
    {
        $namespaces = $this->parseCodeResourceForTest();

        $analyzer = $this->_createAnalyzer();
        $analyzer->analyze($namespaces);

        $actual   = array();
        $expected = array(
            'operators1' => array(
                'hcn'  => (19 + 16) * log(12 + 3, 2),
                'otc'  => 19,
                'odc'  => 16,
                'dotc' => 12,
                'dodc' => 3,
            ),
            'operators2' => array(
                'hcn'  => (14 + 10) * log(12 + 5, 2),
                'otc'  => 14,
                'odc'  => 10,
                'dotc' => 12,
                'dodc' => 5,
            ),
            'operators3' => array(
                'hcn'  => (8 + 4) * log(4 + 1, 2),
                'otc'  => 8,
                'odc'  => 4,
                'dotc' => 4,
                'dodc' => 1,
            ),
            'classes' => array(
                'hcn'  => (13 + 9) * log(7 + 4, 2),
                'otc'  => 13,
                'odc'  => 9,
                'dotc' => 7,
                'dodc' => 4,
            ),
        );

        foreach ($namespaces[0]->getFunctions() as $function) {
            $actual[$function->getName()] = $analyzer->getNodeMetrics($function);
        }

        ksort($expected);
        ksort($actual);

        $this->assertEquals($expected, $actual);
    }
//
//    /**
//     * testCalculateFunctionCCNAndCNN2ProjectMetrics
//     *
//     * @return void
//     */
//    public function testCalculateFunctionCCNAndCNN2ProjectMetrics()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 12, 'ccn2' => 16);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Tests that the analyzer calculates the correct method cc numbers.
//     *
//     * @return void
//     */
//    public function testCalculateMethodCCNAndCNN2()
//    {
//        $namespaces = $this->parseCodeResourceForTest();
//
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze($namespaces);
//
//        $classes = $namespaces[0]->getClasses();
//        $methods = $classes[0]->getMethods();
//
//        $actual   = array();
//        $expected = array(
//            'pdepend1' => array('ccn' => 5, 'ccn2' => 6),
//            'pdepend2' => array('ccn' => 7, 'ccn2' => 10)
//        );
//
//        foreach ($methods as $method) {
//            $actual[$method->getName()] = $analyzer->getNodeMetrics($method);
//        }
//
//        ksort($expected);
//        ksort($actual);
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Tests that the analyzer also detects a conditional expression nested in a
//     * compound expression.
//     *
//     * @return void
//     */
//    public function testCalculateCCNWithConditionalExprInCompoundExpr()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 2, 'ccn2' => 2);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * testCalculateExpectedCCNForDoWhileStatement
//     *
//     * @return void
//     */
//    public function testCalculateExpectedCCNForDoWhileStatement()
//    {
//        $namespaces = $this->parseCodeResourceForTest();
//        $functions = $namespaces[0]->getFunctions();
//
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze($namespaces);
//
//        $this->assertEquals(3, $analyzer->getCcn($functions[0]));
//    }
//
//    /**
//     * testCalculateExpectedCCN2ForDoWhileStatement
//     *
//     * @return void
//     */
//    public function testCalculateExpectedCCN2ForDoWhileStatement()
//    {
//        $namespaces = $this->parseCodeResourceForTest();
//        $functions = $namespaces[0]->getFunctions();
//
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze($namespaces);
//
//        $this->assertEquals(3, $analyzer->getCcn2($functions[0]));
//    }
//
//    /**
//     * Tests that the analyzer ignores the default label in a switch statement.
//     *
//     * @return void
//     */
//    public function testCalculateCCNIgnoresDefaultLabelInSwitchStatement()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 3, 'ccn2' => 3);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Tests that the analyzer counts all case labels in a switch statement.
//     *
//     * @return void
//     */
//    public function testCalculateCCNCountsAllCaseLabelsInSwitchStatement()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 4, 'ccn2' => 4);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Tests that the analyzer detects expressions in a for loop.
//     *
//     * @return void
//     */
//    public function testCalculateCCNDetectsExpressionsInAForLoop()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 2, 'ccn2' => 4);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Tests that the analyzer detects expressions in a while loop.
//     *
//     * @return void
//     */
//    public function testCalculateCCNDetectsExpressionsInAWhileLoop()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 2, 'ccn2' => 4);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Tests that the analyzer aggregates the correct project metrics.
//     *
//     * @return void
//     */
//    public function testCalculateProjectMetrics()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 24, 'ccn2' => 32);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * testAnalyzerAlsoCalculatesCCNAndCCN2OfClosureInMethod
//     *
//     * @return void
//     */
//    public function testAnalyzerAlsoCalculatesCCNAndCCN2OfClosureInMethod()
//    {
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));
//
//        $expected = array('ccn' => 3, 'ccn2' => 3);
//        $actual   = $analyzer->getProjectMetrics();
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * testAnalyzerRestoresExpectedFunctionMetricsFromCache
//     *
//     * @return void
//     * @since 1.0.0
//     */
//    public function testAnalyzerRestoresExpectedFunctionMetricsFromCache()
//    {
//        $namespaces = $this->parseCodeResourceForTest();
//        $functions = $namespaces[0]->getFunctions();
//
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze($namespaces);
//
//        $metrics0 = $analyzer->getNodeMetrics($functions[0]);
//
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze($namespaces);
//
//        $metrics1 = $analyzer->getNodeMetrics($functions[0]);
//
//        $this->assertEquals($metrics0, $metrics1);
//    }
//
//    /**
//     * testAnalyzerRestoresExpectedMethodMetricsFromCache
//     *
//     * @return void
//     * @since 1.0.0
//     */
//    public function testAnalyzerRestoresExpectedMethodMetricsFromCache()
//    {
//        $namespaces = $this->parseCodeResourceForTest();
//        $classes = $namespaces[0]->getClasses();
//        $methods = $classes[0]->getMethods();
//
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze($namespaces);
//
//        $metrics0 = $analyzer->getNodeMetrics($methods[0]);
//
//        $analyzer = $this->_createAnalyzer();
//        $analyzer->analyze($namespaces);
//
//        $metrics1 = $analyzer->getNodeMetrics($methods[0]);
//
//        $this->assertEquals($metrics0, $metrics1);
//    }

    /**
     * Returns a pre configured ccn analyzer.
     *
     * @return \PDepend\Metrics\Analyzer\HalsteadComplexityAnalyzer
     * @since 1.0.0
     */
    private function _createAnalyzer()
    {
        $analyzer = new HalsteadComplexityAnalyzer();
        $analyzer->setCache($this->cache);

        return $analyzer;
    }
}
