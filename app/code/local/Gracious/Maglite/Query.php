<?php

class Gracious_Maglite_Query
{
    protected $wheres = [];

    protected $defaultModel = 'catalog/product';
    protected $model;

    public function __construct($model = null)
    {
        if(!$model){
            $this->model = $this->defaultModel;
        }
    }

    function where($field, $op = null, $value = null, $bool = 'and')
    {
        if ($field instanceof Closure) {
            $subQuery = new Gracious_Maglite_Query();
            $field($subQuery);

            $this->wheres[] = ['type' => 'sub', 'query' => $subQuery, 'bool' => $bool];
            return $this;
        }
        $this->wheres[] = ['type' => 'simple', 'field' => $field, 'op' => $op, 'value' => $value, 'bool' => $bool];
        return $this;
    }

    function orWhere($field, $op = null, $value = null)
    {
        return $this->where($field, $op, $value, 'or');
    }

    function whereAttribute($attr, $op, $value)
    {
        $this->wheres[] = ['type' => 'attr', 'attribute' => $attr, 'op' => $op, 'value' => $value, 'bool' => 'and'];
        return $this;
    }

    function get()
    {
        $collection = $this->getBaseCollection();

        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'attr':

                    $attribute = Mage::getModel('catalog/product')->getResource()->getAttribute($where['attribute']);

                    $operator = $this->parseOperator($where['op']);
                    $collection->addFieldToFilter([['attribute' => $where['attribute'], $operator => $attribute->getSource()->getOptionId($where['value'])]]);
                    break;
                case 'basic':
                    $operator = $this->parseOperator($where['op']);
                    $collection->addFieldToFilter($where['field'], [$operator => $where['value']]);
                    break;

            }
        }
        return $collection;
    }

    protected function parseOperator($operator)
    {
        switch ($operator) {
            default:
            case '=':
                return 'eq';
            case '>':
                return 'gt';
            case '<':
                return 'lt';
            case '>=':
                return 'gte';
            case '<=':
                return 'lte';
        }
    }

    function getBaseCollection()
    {
        $collection = Mage::getModel($this->model)->getResourceCollection()->addAttributeToSelect('*');
        return $collection;
    }

}