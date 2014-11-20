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
    public function testCalculateOperatorsOperandsCounts()
    {
        $namespaces = $this->parseCodeResourceForTest();

        $analyzer = $this->_createAnalyzer();
        $analyzer->analyze($namespaces);

        $actual   = array();
        $expected = array(
            'operators' => array(
                'op'  => 30,
                'od'  => 23,
                'uop' => 20,
                'uod' => 7,
            ),
            'control_structures1' => array(
                'op'  => 19,
                'od'  => 15,
                'uop' => 15,
                'uod' => 5,
            ),
            'control_structures2' => array(
                'op'  => 12,
                'od'  => 12,
                'uop' => 12,
                'uod' => 7,
            ),
            'closures' => array(
                'op'  => 9,
                'od'  => 5,
                'uop' => 5,
                'uod' => 1,
            ),
            'TestClass::method' => array(
                'op'  => 35,
                'od'  => 22,
                'uop' => 11,
                'uod' => 6,
            ),
            'TestTrait::method' => array(
                'op'  => 2,
                'od'  => 2,
                'uop' => 2,
                'uod' => 2,
            ),
            'others' => array(
                'op'  => 20,
                'od'  => 11,
                'uop' => 9,
                'uod' => 7,
            ),
            'key_words' => array(
                'op'  => 4,
                'od'  => 4,
                'uop' => 4,
                'uod' => 2,
            ),
            'strings' => array(
                'op'  => 5,
                'od'  => 7,
                'uop' => 3,
                'uod' => 6,
            ),
            'TestInterface::method' => array(),
        );

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
        $filterKeys = function (&$item) {
            unset($item['hlen'], $item['hvol'], $item['hbug'], $item['heff'], $item['hvoc'], $item['hdiff']);
        };
        array_walk($expected, $filterKeys);
        array_walk($actual, $filterKeys);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests all base Halstead metrics calculations
     *
     * @return void
     */
    public function testHalsteadMetricsCalculation()
    {
        $namespaces = $this->parseCodeResourceForTest();

        $analyzer = $this->_createAnalyzer();
        $analyzer->analyze($namespaces);

        $actual   = array();
        $expected = array(
            'operators' => array(
                'hlen' => 23 + 16,
                'hvol' => (23 + 16) * log(14 + 6, 2),
                'hbug' => ((23 + 16) * log(14 + 6, 2)) / 3000,
                'heff' => ((14 / 2) * (16 / 6)) * ((23 + 16) * log(14 + 6, 2)),
                'hvoc' => 14 + 6,
                'hdiff' => (14 / 2) * (16 / 6),
                'op'  => 23,
                'od'  => 16,
                'uop' => 14,
                'uod' => 6,
            ),
        );

        foreach ($namespaces[0]->getFunctions() as $function) {
            $actual[$function->getName()] = $analyzer->getNodeMetrics($function);
        }

        $this->assertEquals($expected, $actual);
    }

    /**
     * testCalculateMetricsForEmptyFunction
     *
     * @return void
     */
    public function testCalculateMetricsForEmptyFunction()
    {
        $namespaces = $this->parseCodeResourceForTest();

        $analyzer = $this->_createAnalyzer();
        $analyzer->analyze($namespaces);

        $actual   = array();
        $expected = array(
            'operators' => array(
                'hlen' => 0,
                'hvol' => 0,
                'hbug' => 0,
                'heff' => 0,
                'hvoc' => 0,
                'hdiff' => 0,
                'op'  => 0,
                'od'  => 0,
                'uop' => 0,
                'uod' => 0,
            ),
        );
        foreach ($namespaces[0]->getFunctions() as $function) {
            $actual[$function->getName()] = $analyzer->getNodeMetrics($function);
        }

        $this->assertEquals($expected, $actual);
    }

    /**
     * testCalculateMetricsForEmptyFunction
     *
     * @return void
     */
    public function testShouldCalculateConstantsAndNotReturnNotice()
    {
        $namespaces = $this->parseCodeResourceForTest();

        $analyzer = $this->_createAnalyzer();
        $analyzer->analyze($namespaces);

        $actual   = array();
        $expected = array(
            'testAttachFileInvalidElement' => array(
                'hlen' => 2,
                'hvol' => 2,
                'hbug' => 0.00066666666666667,
                'heff' => 1,
                'hvoc' => 2,
                'hdiff' => 0.5,
                'op'  => 1,
                'od'  => 1,
                'uop' => 1,
                'uod' => 1,
            ),
        );
        foreach ($namespaces[0]->getFunctions() as $function) {
            $actual[$function->getName()] = $analyzer->getNodeMetrics($function);
        }

        $this->assertEquals($expected, $actual);
    }

    /**
     * testCalculateProjectMetrics
     *
     * @return void
     */
    public function testCalculateProjectMetrics()
    {
        $analyzer = $this->_createAnalyzer();
        $analyzer->analyze(self::parseTestCaseSource(__METHOD__));

        $expected = array(
            'hlen' => (23 + 16) * 2,
            'hvol' => ((23 + 16) * log(14 + 6, 2)) * 2,
            'hbug' => (((23 + 16) * log(14 + 6, 2)) * 2)/3000,
            'heff' => (((14 / 2) * (16 / 6)) * ((23 + 16) * log(14 + 6, 2))) * 2,
        );
        $actual   = $analyzer->getProjectMetrics();
        $this->assertEquals($expected, $actual);
    }
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
