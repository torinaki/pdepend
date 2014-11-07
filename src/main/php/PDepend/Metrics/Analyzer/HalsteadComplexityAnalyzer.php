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
use PDepend\Metrics\AnalyzerNodeAware;
use PDepend\Metrics\AnalyzerProjectAware;
use PDepend\Source\AST\AbstractASTCallable;
use PDepend\Source\AST\ASTArtifact;
use PDepend\Source\AST\ASTArtifactList;
use PDepend\Source\AST\ASTClass;
use PDepend\Source\AST\ASTDeclareStatement;
use PDepend\Source\AST\ASTFunction;
use PDepend\Source\AST\ASTIfStatement;
use PDepend\Source\AST\ASTInterface;
use PDepend\Source\AST\ASTMethod;
use PDepend\Source\AST\ASTScope;
use PDepend\Source\AST\ASTScopeStatement;
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
class HalsteadComplexityAnalyzer extends AbstractCachingAnalyzer implements AnalyzerNodeAware, AnalyzerProjectAware
{
    /**
     * Type of this analyzer class.
     */
    const CLAZZ = __CLASS__;

    /**
     * Metrics provided by the analyzer implementation.
     */
    const M_HALSTEAD_LENGTH = 'hlen';

    const M_HALSTEAD_VOLUME = 'hvol';

    const M_HALSTEAD_BUGS = 'hbug';

    const M_HALSTEAD_EFFORT = 'heff';

    const M_HALSTEAD_VOCABULARY = 'hvoc';

    const M_HALSTEAD_DIFFICULTY = 'hdiff';

    const M_OPERATORS_COUNT = 'op';

    const M_OPERANDS_COUNT = 'od';

    const M_UNIQUE_OPERATORS_COUNT = 'uop';

    const M_UNIQUE_OPERANDS_COUNT = 'uod';

    private $operatorsDictionary = array();

    private $operandsDictionary = array();

    /**
     * The project Halstead metrics.
     * Vocabulary and Difficulty is not calculated for project.
     *
     * @var array
     */
    private $projectMetrics = array(
        self::M_HALSTEAD_LENGTH => 0,
        self::M_HALSTEAD_VOLUME => 0,
        self::M_HALSTEAD_BUGS   => 0,
        self::M_HALSTEAD_EFFORT => 0,
    );

    /**
     * @var array
     */
    private $classMetrics = array(
        self::M_HALSTEAD_LENGTH => 0,
        self::M_HALSTEAD_VOLUME => 0,
        self::M_HALSTEAD_BUGS   => 0,
        self::M_HALSTEAD_EFFORT => 0,
    );

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

            // Init node metrics
            $this->metrics = array();
            $this->operatorsDictionary = array();
            $this->operandsDictionary = array();

            foreach ($namespaces as $namespace) {
                $namespace->accept($this);
            }

            $this->fireEndAnalyzer();
            $this->unloadCache();
        }
    }

//    /**
//     * Returns the Halstead complexity for the given <b>$node</b> instance.
//     *
//     * @param \PDepend\Source\AST\ASTArtifact $node
//     * @return integer
//     */
//    public function getHcn(ASTArtifact $node)
//    {
//        $metrics = $this->getNodeMetrics($node);
//        if (isset($metrics[self::M_HALSTEAD_COMPLEXITY])) {
//            return $metrics[self::M_HALSTEAD_COMPLEXITY];
//        }
//        return 0;
//    }

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
     * Visits a function node.
     *
     * @param \PDepend\Source\AST\ASTFunction $function
     * @return void
     */
    public function visitFunction(ASTFunction $function)
    {
        $this->fireStartFunction($function);

        if (false === $this->restoreFromCache($function)) {
            $this->calculateComplexity($function);
        }
        $this->updateProjectMetrics($function->getId());

        $this->fireEndFunction($function);
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

        $this->metrics[$class->getId()] = $this->classMetrics;
        foreach ($class->getMethods() as $method) {
            $method->accept($this);
            $this->recalculateOverallMetrics($this->metrics[$class->getId()], $method->getId());
        }

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

        $this->metrics[$trait->getId()] = $this->classMetrics;
        foreach ($trait->getMethods() as $method) {
            $method->accept($this);
            $this->recalculateOverallMetrics($this->metrics[$trait->getId()], $method->getId());
        }

        $this->fireEndTrait($trait);
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
            $this->calculateComplexity($method);
        }
        $this->updateProjectMetrics($method->getId());

        $this->fireEndMethod($method);
    }

    /**
     * Visits methods, functions or closures and calculated their complexity.
     *
     * @param \PDepend\Source\AST\AbstractASTCallable $callable
     * @return void
     * @since 0.9.8
     */
    public function calculateComplexity(AbstractASTCallable $callable)
    {
        $data = array(
            self::M_HALSTEAD_LENGTH        => 0,
            self::M_HALSTEAD_VOLUME        => 0,
            self::M_HALSTEAD_BUGS          => 0,
            self::M_HALSTEAD_EFFORT        => 0,
            self::M_HALSTEAD_VOCABULARY    => 0,
            self::M_HALSTEAD_DIFFICULTY    => 0,
            self::M_OPERATORS_COUNT        => 0,
            self::M_OPERANDS_COUNT         => 0,
            self::M_UNIQUE_OPERATORS_COUNT => 0,
            self::M_UNIQUE_OPERANDS_COUNT  => 0,
        );
        $this->operandsDictionary = $this->operatorsDictionary = array();

        $children = $callable->getChildren();
        if (isset($children[1]) && $children[1] instanceof ASTScope) {
            foreach ($children[1]->getChildren() as $child) {
                $data = $child->accept($this, $data);
            }
        }

        // The Halstead length (LTH) is OP+OD
        $data[self::M_HALSTEAD_LENGTH] =
            $data[self::M_OPERATORS_COUNT] + $data[self::M_OPERANDS_COUNT];

        // The Halstead Vocabulary (VOC) UOP+UOD
        $data[self::M_HALSTEAD_VOCABULARY] =
            $data[self::M_UNIQUE_OPERATORS_COUNT] + $data[self::M_UNIQUE_OPERANDS_COUNT];

        // The Halstead Difficulty (DIF) is (UOP/2) * (OD/UOD)
        $data[self::M_HALSTEAD_DIFFICULTY] =
            ($data[self::M_UNIQUE_OPERATORS_COUNT] / 2) * ($data[self::M_OPERANDS_COUNT] / $data[self::M_UNIQUE_OPERANDS_COUNT]);

        // Halstead Volume (VOL) = LTH * log2(VOC)
        $data[self::M_HALSTEAD_VOLUME] =
            $data[self::M_HALSTEAD_LENGTH] * log($data[self::M_HALSTEAD_VOCABULARY], 2);

        // Halstead Effort (EFF) = DIF * VOL
        $data[self::M_HALSTEAD_EFFORT] =
            $data[self::M_HALSTEAD_DIFFICULTY] * $data[self::M_HALSTEAD_VOLUME];

        // Halstead Bugs (BUG) = VOL/3000 or EFF^(2/3)/3000
        $data[self::M_HALSTEAD_BUGS] =
            $data[self::M_HALSTEAD_VOLUME] / 3000;

        $this->metrics[$callable->getId()] = $data;
    }

    /**
     * Stores the complexity of a node and updates the corresponding project
     * values.
     *
     * @param string $nodeId Identifier of the analyzed item.
     *
     * @return void
     * @since 1.0.0
     */
    private function updateProjectMetrics($nodeId)
    {
        $this->recalculateOverallMetrics($this->projectMetrics, $nodeId);
    }

    /**
     * @param array $metrics
     * @param string $nodeId
     */
    private function recalculateOverallMetrics(&$metrics, $nodeId)
    {
        $metrics[self::M_HALSTEAD_LENGTH] += $this->metrics[$nodeId][self::M_HALSTEAD_LENGTH];
        $metrics[self::M_HALSTEAD_VOLUME] += $this->metrics[$nodeId][self::M_HALSTEAD_VOLUME];
        $metrics[self::M_HALSTEAD_BUGS] = $metrics[self::M_HALSTEAD_VOLUME] / 3000;
        $metrics[self::M_HALSTEAD_EFFORT] += $this->metrics[$nodeId][self::M_HALSTEAD_EFFORT];
    }

    /**
     * Visits a boolean AND-expression.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitBooleanAndExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a boolean OR-expression.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitBooleanOrExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a switch label.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitSwitchLabel($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a switch label.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitSwitchStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'switch'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitCatchStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitTryStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits an elseif statement.
     *
     * @param \PDepend\Source\AST\ASTElseIfStatement $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitElseIfStatement($node, $data)
    {
        if ($node->hasElse()) {
            $childNode = $node->getChild(2);
            if ($childNode instanceof ASTIfStatement || $childNode instanceof ASTScopeStatement) {
                $data = $this->incrementOperatorCount($data, 'else');
            }
        }
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a for statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitForStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a foreach statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitForeachStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits an if statement.
     *
     * @param \PDepend\Source\AST\ASTIfStatement $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitIfStatement($node, $data)
    {
        if ($node->hasElse()) {
            $childNode = $node->getChild(2);
            if ($childNode instanceof ASTIfStatement || $childNode instanceof ASTScopeStatement) {
                $data = $this->incrementOperatorCount($data, 'else');
            }
        }
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a logical AND expression.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitLogicalAndExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a logical OR expression.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitLogicalOrExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a while-statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitWhileStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a do/while-statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.12
     */
    public function visitDoWhileStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Each line contain end of line operator ";"
     *
     * @param $node
     * @param $data
     *
     * @return mixed
     */
    public function visitStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, ';'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitAssignmentExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitVariable($node, $data)
    {
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitLiteral($node, $data)
    {
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage(), true));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitClassOrInterfaceReference($node, $data)
    {
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitCloneExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a ternary operator.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     */
    public function visitExpression($node, $data)
    {
        $image = $node->getImage();
        if ($image) {
            $data = $this->incrementOperatorCount($data, $image);
        }
        return $this->visit($node, $data);
    }

    /**
     * Visits a ternary operator.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     */
    public function visitConditionalExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitUnaryExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitHeredoc($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, '<<<'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitArray($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'array'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitArrayElement($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitArrayIndexExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, '[]'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitBreakStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitCastExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitClosure($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'function'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitReturnStatement($node, $data)
    {
        $data = $this->incrementOperatorCount($data, ';');
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitMemberPrimaryPrefix($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitClassFqnPostfix($node, $data)
    {
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitAllocationExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitClassReference($node, $data)
    {
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitCompoundExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, '{}'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitCompoundVariable($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitMethodPostfix($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitIdentifier($node, $data)
    {
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitVariableVariable($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, '${}'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitPreDecrementExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, '--'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitPreIncrementExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, '++'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitPostfixExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitConstantPostfix($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitArguments($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitComment($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitConstant($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitConstantDeclarator($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitConstantDefinition($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitContinueStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * @param ASTDeclareStatement $node
     * @param array $data
     *
     * @return mixed
     */
    public function visitDeclareStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'declare'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitEchoStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitEvalExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitExitExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitFieldDeclaration($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitForInit($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitFormalParameter($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitFormalParameters($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitForUpdate($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitFunctionPostfix($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitGlobalStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'global'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitGotoStatement($node, $data)
    {
        $data = $this->incrementOperatorCount($data, 'goto');
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitIncludeExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'include'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitInstanceOfExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitIssetExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'isset'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitLabelStatement($node, $data)
    {
        $data = $this->incrementOperatorCount($data, ':');
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitListExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitLogicalXorExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitParentReference($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitPrimitiveType($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitPropertyPostfix($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitRequireExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'require'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitSelfReference($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitShiftLeftExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitShiftRightExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitStaticReference($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitStaticVariableDeclaration($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitVariableDeclarator($node, $data)
    {
        return $this->visit($node, $this->incrementOperandCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitString($node, $data)
    {
        return $this->visit($node, $data);
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitStringIndexExpression($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, '{}'));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitThrowStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, $node->getImage()));
    }

    /**
     * Visits a catch statement.
     *
     * @param \PDepend\Source\AST\ASTNode $node The currently visited node.
     * @param array(string=>integer)   $data The previously calculated ccn values.
     *
     * @return array(string=>integer)
     * @since 0.9.8
     */
    public function visitUnsetStatement($node, $data)
    {
        return $this->visit($node, $this->incrementOperatorCount($data, 'unset'));
    }

    /**
     * @param array  $data
     * @param string $operator
     *
     * @return array
     */
    private function incrementOperatorCount($data, $operator)
    {
        if (!$operator) {
            throw new \InvalidArgumentException('Operator string can\'t be empty');
        }
        ++$data[self::M_OPERATORS_COUNT];
        if (!in_array($operator, $this->operatorsDictionary)) {
            $this->operatorsDictionary[] = $operator;
            ++$data[self::M_UNIQUE_OPERATORS_COUNT];
        }
        return $data;
    }

    /**
     * @param array  $data
     * @param string $operand
     * @param bool   $forceUnique count provided operator as unique anyway
     *
     * @return array
     */
    private function incrementOperandCount($data, $operand, $forceUnique = false)
    {
        ++$data[self::M_OPERANDS_COUNT];
        if ($forceUnique || !in_array($operand, $this->operandsDictionary)) {
            $this->operandsDictionary[] = $operand;
            ++$data[self::M_UNIQUE_OPERANDS_COUNT];
        }
        return $data;
    }
}
