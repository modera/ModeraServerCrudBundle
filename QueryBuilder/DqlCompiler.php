<?php

namespace Modera\ServerCrudBundle\QueryBuilder;

use Modera\ServerCrudBundle\QueryBuilder\Parsing\Expression;

/**
 * @internal
 *
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class DqlCompiler
{
    private ExpressionManager $exprMgr;

    public function __construct(ExpressionManager $exprMgr)
    {
        $this->exprMgr = $exprMgr;
    }

    public function compile(Expression $expression, DoctrineQueryBuilderParametersBinder $binder): string
    {
        if ($expression->getFunction()) {
            /** @var array<int, string> $compiledArgs */
            $compiledArgs = [];
            foreach ($expression->getFunctionArgs() as $arg) {
                if ($arg instanceof Expression) {
                    $compiledArgs[] = $this->compile($arg, $binder);
                } else {
                    $compiledArgs[] = $this->resolveArgument($arg, $binder);
                }
            }

            $result = $expression->getFunction().'('.\implode(', ', $compiledArgs).')';
        } else {
            /** @var string $arg */
            $arg = $expression->getExpression();
            $result = $this->resolveArgument($arg, $binder);
        }

        if ($expression->getAlias()) {
            $result .= ' AS '.($expression->isHidden() ? 'HIDDEN ' : '').$expression->getAlias();
        }

        return $result;
    }

    private function resolveArgument(string $arg, DoctrineQueryBuilderParametersBinder $binder): string
    {
        if (':' == $arg[0]) { // a field is being referenced
            return $this->exprMgr->getDqlPropertyName(\substr($arg, 1));
        } else {
            return '?'.($binder->bind($arg) - 1);
        }
    }
}
