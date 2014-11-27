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

use PDepend\Metrics\AbstractCachingAnalyzer;
use PDepend\Metrics\AggregateAnalyzer;
use PDepend\Metrics\Analyzer;
use PDepend\Metrics\AnalyzerNodeAware;
use PDepend\Metrics\AnalyzerProjectAware;
use PDepend\Source\AST\AbstractASTCallable;
use PDepend\Source\AST\ASTArtifact;
use PDepend\Source\AST\ASTArtifactList;
use PDepend\Source\AST\ASTClass;
use PDepend\Source\AST\ASTFunction;
use PDepend\Source\AST\ASTInterface;
use PDepend\Source\AST\ASTMethod;
use PDepend\Source\AST\ASTNamespace;
use PDepend\Source\AST\ASTTrait;

/**
 * This class calculates the Halstead Complexity Number(HCN) for the project,
 * methods and functions.
 *
 * @copyright 2008-2014 Dmitry Balabka. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 *
 * @method int visit() visit(\PDepend\Source\AST\ASTNode $int1, array $int2)
 */
class MaintainabilityIndexAnalyzer extends AbstractCachingAnalyzer implements AggregateAnalyzer, AnalyzerNodeAware, AnalyzerProjectAware
{
    /**
     * Type of this analyzer class.
     */
    const CLAZZ = __CLASS__;

    /**
     * Metrics provided by the analyzer implementation.
     */
    const M_MAINTAINABILITY_INDEX = 'mi';

    const M_MAINTAINABILITY_INDEX2 = 'mi2';

    const M_MAINTAINABILITY_INDEX_NO_COMMENTS = 'minc';

    /**
     * The project Maintainability Index metrics.
     *
     * @var array
     */
    private $projectMetrics = array(
        self::M_MAINTAINABILITY_INDEX               => 0,
        self::M_MAINTAINABILITY_INDEX_NO_COMMENTS   => 0,
    );

    /**
     * @var HalsteadComplexityAnalyzer
     */
    private $halsteadAnalyzer;

    /**
     * @var NodeCountAnalyzer
     */
    private $nodeCountAnalyzer;

    /**
     * @var CyclomaticComplexityAnalyzer
     */
    private $ccnAnalyzer;

    /**
     * @var NodeLocAnalyzer
     */
    private $locAnalyzer;

    /**
     * Project overall metrics container
     *
     * @var array
     */
    private $overallMetrics = array();

    /**
     * Returns an array with analyzer class names that are required by the MI analyzer.
     *
     * @return array(string)
     */
    public function getRequiredAnalyzers()
    {
        return array(
            'PDepend\\Metrics\\Analyzer\\HalsteadComplexityAnalyzer',
            'PDepend\\Metrics\\Analyzer\\NodeCountAnalyzer',
            'PDepend\\Metrics\\Analyzer\\CyclomaticComplexityAnalyzer',
            'PDepend\\Metrics\\Analyzer\\NodeLocAnalyzer',
        );
    }

    /**
     * Adds an analyzer that this analyzer depends on.
     *
     * @param \PDepend\Metrics\Analyzer $analyzer
     * @return void
     */
    public function addAnalyzer(Analyzer $analyzer)
    {
        if ($analyzer instanceof HalsteadComplexityAnalyzer) {
            $this->halsteadAnalyzer = $analyzer;
        } elseif ($analyzer instanceof NodeCountAnalyzer) {
            $this->nodeCountAnalyzer = $analyzer;
        } elseif ($analyzer instanceof CyclomaticComplexityAnalyzer) {
            $this->ccnAnalyzer = $analyzer;
        } elseif ($analyzer instanceof NodeLocAnalyzer) {
            $this->locAnalyzer = $analyzer;
        } else {
            throw new \InvalidArgumentException('Invalid analyzer provided.');
        }
    }

    /**
     * Processes all {@link \PDepend\Source\AST\ASTNamespace} code nodes.
     *
     * @param \PDepend\Source\AST\ASTNamespace[] $namespaces
     * @return void
     */
    public function analyze($namespaces)
    {
        if ($this->metrics === null) {
            $this->loadCache();
            $this->fireStartAnalyzer();

            $this->ccnAnalyzer->analyze($namespaces);
            $this->halsteadAnalyzer->analyze($namespaces);
            $this->locAnalyzer->analyze($namespaces);
            $this->nodeCountAnalyzer->analyze($namespaces);

            // Init node metrics
            $this->metrics = array();
            $this->overallMetrics = array(
                'volume_sum' => 0,
                'cnn2_sum'   => 0,
                'loc_sum'    => 0,
                'percm_sum'  => 0,
                'count'      => 0,
            );

            foreach ($namespaces as $namespace) {
                $namespace->accept($this);
            }
            $this->updateProjectMetrics();

            $this->fireEndAnalyzer();
            $this->unloadCache();
        }
    }

    /**
     * This method will return an <b>array</b> with all generated metric values
     * for the given <b>$node</b>. If there are no metrics for the requested
     * node, this method will return an empty <b>array</b>.
     *
     * @param \PDepend\Source\AST\ASTArtifact $artifact
     * @return array
     */
    public function getNodeMetrics(ASTArtifact $artifact)
    {
        if (isset($this->metrics[$artifact->getId()])) {
            return $this->metrics[$artifact->getId()];
        }
        return array();
    }

    /**
     * Provides the project summary metrics as an <b>array</b>.
     *
     * @return array
     */
    public function getProjectMetrics()
    {
        return $this->projectMetrics;
    }

    /**
     * Visits a namespace node.
     *
     * @param ASTNamespace $namespace
     * @return void
     */
    public function visitNamespace(ASTNamespace $namespace)
    {
        $this->fireStartNamespace($namespace);

        $metrics = $this->overallMetrics;
        foreach ($namespace->getClasses() as $class) {
            $class->accept($this);
        }
        foreach ($namespace->getTraits() as $trait) {
            $trait->accept($this);
        }
        foreach ($namespace->getFunctions() as $function) {
            $function->accept($this);
        }
        $this->addNodeAverageMetrics($namespace, $this->calculateOverallMetricsDeltas($metrics));

        $this->fireEndNamespace($namespace);
    }


    /**
     * Visits a code interface object.
     *
     * @param \PDepend\Source\AST\ASTInterface $interface
     * @return void
     */
    public function visitInterface(ASTInterface $interface)
    {
        // Empty visit method, we don't want interface metrics
    }

    /**
     * Visits a class node.
     *
     * @param ASTClass $class
     * @return void
     */
    public function visitClass(ASTClass $class)
    {
        $this->fireStartClass($class);

        $class->getCompilationUnit()->accept($this);

        $metrics = $this->overallMetrics;
        foreach ($class->getMethods() as $method) {
            $method->accept($this);
        }
        $this->addNodeAverageMetrics($class, $this->calculateOverallMetricsDeltas($metrics));

        $this->fireEndClass($class);
    }

    /**
     * Visits a trait node.
     *
     * @param \PDepend\Source\AST\ASTTrait $trait
     * @return void
     * @since 1.0.0
     */
    public function visitTrait(ASTTrait $trait)
    {
        $this->fireStartTrait($trait);

        $trait->getCompilationUnit()->accept($this);

        $metrics = $this->overallMetrics;
        foreach ($trait->getMethods() as $method) {
            $method->accept($this);
        }
        $this->addNodeAverageMetrics($trait, $this->calculateOverallMetricsDeltas($metrics));

        $this->fireEndTrait($trait);
    }

    /**
     * Visits a function node.
     *
     * @param \PDepend\Source\AST\ASTFunction $function
     * @return void
     */
    public function visitFunction(ASTFunction $function)
    {
        $this->fireStartFunction($function);

        if (false === $this->restoreFromCache($function)) {
            $this->addCallableNodeMetrics($function);
        }

        $this->fireEndFunction($function);
    }

    /**
     * Visits a method node.
     *
     * @param \PDepend\Source\AST\ASTMethod $method
     * @return void
     */
    public function visitMethod(ASTMethod $method)
    {
        $this->fireStartMethod($method);

        if (false === $this->restoreFromCache($method)) {
            $this->addCallableNodeMetrics($method);
        }

        $this->fireEndMethod($method);
    }

    private function addCallableNodeMetrics(AbstractASTCallable $node)
    {
        $halsteadMetrics = $this->halsteadAnalyzer->getNodeMetrics($node);
        $locMetrics = $this->locAnalyzer->getNodeMetrics($node);
        $metrics = array(
            'volume_avg' => $halsteadMetrics[HalsteadComplexityAnalyzer::M_HALSTEAD_VOLUME],
            'cnn2_avg'   => $this->ccnAnalyzer->getCcn2($node),
            'loc_avg'    => $locMetrics[NodeLocAnalyzer::M_NON_COMMENT_LINES_OF_CODE],
            'percm_avg'     => $locMetrics[NodeLocAnalyzer::M_COMMENT_LINES_OF_CODE] /
                ($locMetrics[NodeLocAnalyzer::M_EXECUTABLE_LINES_OF_CODE] +
                    $locMetrics[NodeLocAnalyzer::M_COMMENT_LINES_OF_CODE]),
        );
        $this->addNodeMetrics($node, $metrics);
        $this->overallMetrics['volume_sum'] += $metrics['volume_avg'];
        $this->overallMetrics['cnn2_sum'] += $metrics['cnn2_avg'];
        $this->overallMetrics['loc_sum'] += $metrics['loc_avg'];
        $this->overallMetrics['percm_sum'] += $metrics['percm_avg'];
        $this->overallMetrics['count']++;
    }

    private function addNodeAverageMetrics(
        ASTArtifact $node,
        $overallMetrics
    ) {
        $this->addNodeMetrics($node, $this->calculateAverageNodeMetrics($overallMetrics));
    }

    private function addNodeMetrics(ASTArtifact $node, array $metrics)
    {
        $nodeId = $node->getId();
        $this->metrics[$nodeId] = $this->calculateMaintainabilityIndex($metrics);
    }

    private function calculateAverageNodeMetrics(array $overallMetrics)
    {
        return array(
            'volume_avg' => $overallMetrics['volume_sum'] / $overallMetrics['count'],
            'cnn2_avg' => $overallMetrics['cnn2_sum'] / $overallMetrics['count'],
            'loc_avg' => $overallMetrics['loc_sum'] / $overallMetrics['count'],
            'percm_avg' => $overallMetrics['percm_sum'] / $overallMetrics['count'],
        );
    }

    private function calculateMaintainabilityIndex(array $metrics)
    {
        $mi = array();
        $mi[self::M_MAINTAINABILITY_INDEX_NO_COMMENTS] =
            171 - 5.2 * log($metrics['volume_avg']) - 0.23 * $metrics['cnn2_avg'] - 16.2 * log($metrics['loc_avg']);
        $mi[self::M_MAINTAINABILITY_INDEX] = $mi[self::M_MAINTAINABILITY_INDEX_NO_COMMENTS] + 50 * sin(sqrt(2.46 * $metrics['percm_avg']));
        $mi[self::M_MAINTAINABILITY_INDEX2] = $mi[self::M_MAINTAINABILITY_INDEX_NO_COMMENTS] + 50 * sin(sqrt(0.20 * $metrics['percm_avg']));
        return $mi;
    }

    private function calculateOverallMetricsDeltas(array $metricsState)
    {
        return array(
            'volume_sum' => $this->overallMetrics['volume_sum'] - $metricsState['volume_sum'],
            'cnn2_sum'   => $this->overallMetrics['cnn2_sum'] - $metricsState['cnn2_sum'],
            'loc_sum'    => $this->overallMetrics['loc_sum'] - $metricsState['loc_sum'],
            'percm_sum'  => $this->overallMetrics['percm_sum'] - $metricsState['percm_sum'],
            'count'      => $this->overallMetrics['count'] - $metricsState['count'],
        );
    }

    private function updateProjectMetrics()
    {
        $this->projectMetrics = $this->calculateMaintainabilityIndex(
            $this->calculateAverageNodeMetrics($this->overallMetrics)
        );
    }
}
