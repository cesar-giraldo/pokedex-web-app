<?php

declare(strict_types=1);

namespace App\Doctrine\Dql;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

use function sprintf;

/**
 * Casts a JSON column to text so LIKE comparisons work on PostgreSQL and MySQL.
 */
final class JsonAsTextFunction extends FunctionNode
{
    public Node $expression;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->expression = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        $expressionSql = $this->expression->dispatch($sqlWalker);

        if ($platform instanceof PostgreSQLPlatform) {
            return sprintf('CAST(%s AS TEXT)', $expressionSql);
        }

        return sprintf('CAST(%s AS CHAR)', $expressionSql);
    }
}
