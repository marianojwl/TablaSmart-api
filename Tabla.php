<?php
namespace marianojwl\TablaSmart {
  class Tabla {
    protected string $db;
    protected string $name;
    protected string $alias;
    protected string $joinType = '';
    protected string $joinCondition = '';

    /**
     * Constructor for the Tabla class.
     * @param string $db The database name.
     * @param string $name The name of the table.
     * @param string $alias The alias for the table.
     * 
     */
    public function __construct($db, $name, $alias, $joinType = '', $joinCondition = '') {
      $this->db = $db;
      $this->name = $name;
      $this->alias = $alias;
      $this->joinType = $joinType;
      $this->joinCondition = $joinCondition;
    }

    public function getDb() {
      return $this->db;
    }

    public function getName() {
      return $this->name;
    }

    public function getAlias() {
      return $this->alias;
    }

    public function getJoinType() {
      return $this->joinType;
    }

    public function getJoinCondition() {
      return $this->joinCondition;
    }
  }
}