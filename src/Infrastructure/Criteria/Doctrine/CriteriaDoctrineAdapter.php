<?php

namespace Pccomponentes\CriteriaDoctrineAdapter\Infrastructure\Criteria\Doctrine;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Pccomponentes\Criteria\Domain\Criteria\AndFilter;
use Pccomponentes\Criteria\Domain\Criteria\Criteria;
use Doctrine\Common\Collections\Criteria as DoctrineCriteria;
use Pccomponentes\Criteria\Domain\Criteria\Filter;
use Pccomponentes\Criteria\Domain\Criteria\FilterField;
use Pccomponentes\Criteria\Domain\Criteria\FilterInterface;
use Pccomponentes\Criteria\Domain\Criteria\FilterVisitorInterface;
use Pccomponentes\Criteria\Domain\Criteria\OrderBy;
use Pccomponentes\Criteria\Domain\Criteria\OrFilter;

final class CriteriaDoctrineAdapter implements FilterVisitorInterface
{
    private $criteria;
    private $criteriaToDoctrineFields;

    public function __construct(Criteria $criteria, array $criteriaToDoctrineFields = [])
    {
        $this->criteria = $criteria;
        $this->criteriaToDoctrineFields = $criteriaToDoctrineFields;
    }

    public static function convert(Criteria $criteria, array $criteriaToDoctrineFields = [])
    {
        $converter = new self($criteria, $criteriaToDoctrineFields);

        return $converter->convertToDoctrineCriteria();
    }

    public function convertToDoctrineCriteria()
    {
        return new DoctrineCriteria(
            $this->buildExpressions($this->criteria),
            $this->formatOrder($this->criteria),
            $this->criteria->offset(),
            $this->criteria->limit()
        );
    }

    private function buildExpressions(Criteria $criteria)
    {
        $expressions = [];
        /** @var FilterInterface $theFilter */
        foreach ($criteria->filters() as $theFilter) {
            $expressions[] = $this->buildFilterExpression($theFilter);
        }

        if (empty($expressions)) {
            return null;
        }

        return new CompositeExpression(
            CompositeExpression::TYPE_AND,
            $expressions
        );
    }

    private function buildFilterExpression(FilterInterface $filter)
    {
        $accept = $filter->accept($this);
        return $accept;
    }

    public function visitAnd(AndFilter $filter)
    {
        return new CompositeExpression(
            CompositeExpression::TYPE_AND,
            [$this->buildFilterExpression($filter->left()), $this->buildFilterExpression($filter->right())]
        );
    }

    public function visitOr(OrFilter $filter)
    {
        return new CompositeExpression(
            CompositeExpression::TYPE_OR,
            [$this->buildFilterExpression($filter->left()), $this->buildFilterExpression($filter->right())]
        );
    }

    public function visitFilter(Filter $filter)
    {
        $field = $this->mapFieldValue($filter->field());
        $value = $filter->value()->value();

        return new Comparison($field, $filter->operator()->value(), $value);
    }

    private function mapFieldValue(FilterField $field)
    {
        return array_key_exists($field->value(), $this->criteriaToDoctrineFields)
            ? $this->criteriaToDoctrineFields[$field->value()]
            : $field->value();
    }

    private function formatOrder(Criteria $criteria)
    {
        if (false === $criteria->hasOrder()) {
            return null;
        }

        return [$this->mapOrderBy($criteria->order()->orderBy()) => $criteria->order()->orderType()];
    }

    private function mapOrderBy(OrderBy $order)
    {
        return array_key_exists($order->value(), $this->criteriaToDoctrineFields)
            ? $this->criteriaToDoctrineFields[$order->value()]
            : $order->value();
    }
}
