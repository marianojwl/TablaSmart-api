<?php
namespace marianojwl\TablaSmart {
  class Columna {
    protected $key;
    protected $label;
    protected $expression;
    protected $isAggregate = false;
    protected $filterBy = null;
    protected $hidden = false;

    /**
     * Constructor for the Columna class.
     * @param string $key The key of the column.
     * @param string $label The label of the column.
     * @param string $expression The SQL expression for the column.
     * @param bool $isAggregate Whether the column is an aggregate function.
     * @return void
     * 
     */
    public function __construct($key, $label, $expression, $isAggregate=false, $filterBy = null, $hidden = false) {
      $this->key = $key;
      $this->label = $label;
      $this->expression = $expression;
      $this->isAggregate = $isAggregate; 
      $this->filterBy = $filterBy;
      $this->hidden = $hidden;
    }

    public function getKey() {
      return $this->key;
    }

    public function getLabel() {
      return $this->label;
    }

    public function getExpression() {
      return $this->expression;
    }

    public function isAggregate() {
      return $this->isAggregate;
    }

    public function getFilterBy() {
      return $this->filterBy;
    }

    public function isHidden() {
      return $this->hidden;
    }

    public function getAsAssoc() {
      return [
        "key" => $this->key,
        "label" => $this->label,
        "expression" => $this->expression,
        "isAggregate" => $this->isAggregate,
        "filterBy" => $this->filterBy,
        "hidden" => $this->hidden
      ];
    }

  }
}