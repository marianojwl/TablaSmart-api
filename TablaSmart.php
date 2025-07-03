<?php
namespace marianojwl\TablaSmart {
  class TablaSmart {
    protected \mysqli $conn;
    protected Tabla $tablaPrincipal;
    protected array $columnas = [];
    protected array $joinTables = [];

    public function __construct(\mysqli $conn){
      $this->conn = $conn;
    }

    public function setTablaPrincipal(Tabla $tabla) {
      $this->tablaPrincipal = $tabla;
      return $this;
    }

    public function addJoinTable(Tabla $tabla) {
      $this->joinTables[] = $tabla;
      return $this;
    }

    public function addColumna(Columna $columna) {
      $this->columnas[] = $columna;
      return $this;
    }

    public function getColumnsMeta() {
      return array_map(function($c) {
          return $c->getAsAssoc();
        }, $this->columnas);
    }

    protected function hasAggregateColumns() {
      return count(array_filter($this->columnas, function($c) {
        return $c->isAggregate();
      })) > 0;
    }

    protected function getTotalCountQuery() {
      return $this->getSelectQuery(1, 1, '1', 'ASC', true);
    }

    protected function getTotalsQuery() {
      return $this->getSelectQuery(1, 1, '1', 'ASC', false, true);
    }

    protected function getSearchTerms() {
      // get rid of any non-alphanumeric characters, but spaces
      $searchQuery = $_GET["search"]??""; //preg_replace('/[^a-zA-Z0-9ñÑ\s]/', '', $search??"");

      if($searchQuery === "") {
        return [];
      }

      // get rid of multiple spaces
      $searchQuery = preg_replace('/\s+/', ' ', $searchQuery);

      // to lower
      $searchQuery = mb_strtolower($searchQuery, 'UTF-8');

      // replace special characters with regular alphanumeric characters, eg: ó -> o, ü -> u
      $searchQuery = preg_replace('/[áàäâ]/u', 'a', $searchQuery);
      $searchQuery = preg_replace('/[éèëê]/u', 'e', $searchQuery);
      $searchQuery = preg_replace('/[íìïî]/u', 'i', $searchQuery);
      $searchQuery = preg_replace('/[óòöô]/u', 'o', $searchQuery);
      $searchQuery = preg_replace('/[úùüû]/u', 'u', $searchQuery);

      // get terms
      $searchTerms = explode(' ', $searchQuery);

      return $searchTerms;
    }

    protected function getSelectQuery($page=1, $limit=10, $orderBy='1', $orderDir='ASC', $isTotalCountQuery=false, $isTotalsQuery=false, $filtering=null) {
      $q = "SELECT ";

      // Filtering
      if($filtering) {
        $q.= "DISTINCT ";
      }

      $q .= PHP_EOL;

      // Fields to select
      if($isTotalCountQuery) {
        // Total count query
        $q .= "COUNT(*) AS total_count" . PHP_EOL;
      } elseif($isTotalsQuery) {
        // Aggregate columns
        $q .= implode(", " . PHP_EOL, array_map(function($c) {
          return $c->getExpression() . " AS " . $c->getKey();
        }, array_filter($this->columnas, function($c) {
          return $c->isAggregate();
        }))) . PHP_EOL;
      } elseif($filtering){
        // Filter Column
        $filterColumn = array_filter($this->columnas, function($c) use ($filtering) {
          return $c->getKey() === $filtering;
        });
        $filterColumn = reset($filterColumn);

        // Filtering query
        $q .= implode(", " . PHP_EOL, array_map(function($c) {
          return $c->getExpression() . " AS " . $c->getKey();
        }, array_filter($this->columnas, function($c) use ($filterColumn) {
          return $c->getKey() === $filterColumn->getKey() || $c->getKey() === $filterColumn->getFilterBy();
        }))) . PHP_EOL;
      } else {
        // Regular Select
        $q .= implode(", " . PHP_EOL, array_map(function($c) {
          return $c->getExpression() . " AS " . $c->getKey();
        }, $this->columnas)) . PHP_EOL;
      }

      // Main Table
      $q .= " FROM " . $this->tablaPrincipal->getDb() . "." . $this->tablaPrincipal->getName() . " " . $this->tablaPrincipal->getAlias() . " ". PHP_EOL;

      // Joins
      $q .= implode(" " . PHP_EOL , array_map(function($jt) {
        return $jt->getJoinType() . " " . $jt->getDb() . "." . $jt->getName() . " " . $jt->getAlias() . " ON " . $jt->getJoinCondition();
      }, $this->joinTables)) . PHP_EOL;

      // Where Clause
      $q .= " WHERE 1=1 AND " . PHP_EOL;

      $nonAggregateFilters = array_map(function($c) {
        $key = $c->getKey();
        return "( CASE WHEN " . $c->getExpression() . " IS NULL THEN '' ELSE " . $c->getExpression() . " END IN (" . implode(", ", array_map(function($v) use ($c) {
            return  "'" . $this->conn->real_escape_string($v) . "'";
          }, $_GET[$key] )) . "))";
      }, array_filter($this->columnas, function($c) {
        return key_exists($c->getKey(), $_GET) && is_array($_GET[$c->getKey()]) && !$c->isAggregate();
      }));

      // Search Terms
      $searchTerms = $this->getSearchTerms();
      $searchFilters = array_map(function($term) {
        return "(" . implode(" OR ", array_map(function($c) use ($term) {
          return $c->getExpression() . " LIKE '%" . $this->conn->real_escape_string($term) . "%'";
        }, array_filter($this->columnas, function($c) {
          return !$c->isHidden() && !$c->isAggregate();
        }))) . ")";
      }, $searchTerms);
      

      $q .= implode(" AND " . PHP_EOL, [...$nonAggregateFilters, ...$searchFilters]) . PHP_EOL;

      $q = rtrim($q, " AND " . PHP_EOL); // Remove trailing AND

      // die($q);

      // Group By
      if($this->hasAggregateColumns() && !$isTotalCountQuery && !$isTotalsQuery) {
        $q .= " GROUP BY " . PHP_EOL;
        $q .= implode(", " . PHP_EOL, array_map(function($c) {
          return $c->getExpression();
        }, array_filter($this->columnas, function($c) {
          return !$c->isAggregate();
        }))) . PHP_EOL;

        // Having Clause
        $aggregateFilters = array_map(function($c) {
          $key = $c->getKey();
          return $c->getExpression() . " IN (" . implode(", ", array_map(function($value) use ($c) {
              return is_numeric($value) ? floatval($value) : $value;
            }, $_GET[$key])) . ")";
        }, array_filter($this->columnas, function($c) {
          return key_exists($c->getKey(), $_GET) && is_array($_GET[$c->getKey()]) && $c->isAggregate();
        }));

        if(count($aggregateFilters) > 0) {
          $q .= " HAVING " . PHP_EOL;
          $q .= implode(" AND " . PHP_EOL, $aggregateFilters) . PHP_EOL;
        }
      }


      // Order And Pagination
      if(!$isTotalCountQuery && !$isTotalsQuery) {
        if($filtering) {
          // If filtering is applied, order by the filtered colum
          $orderBy = $this->conn->real_escape_string($filtering);
          $orderDir = 'ASC'; // Default order direction for filtering
        $q .= " ORDER BY " . $orderBy . " " . $orderDir . PHP_EOL;
        } else {
          $q .= " ORDER BY " . $orderBy . " " . $orderDir . PHP_EOL;
          $q .= " LIMIT " . intval($limit) . " OFFSET " . (intval($page) - 1) * intval($limit);
        }
      }
      // die($q);
      return $q;
    }

    protected function runQuery($query) {
      $result = $this->conn->query($query);
      if (!$result) {
        throw new \Exception("Database query failed: " . $this->conn->error);
      }
      return $result;
    }

    protected function getRows(){
      // &limit=25&page=1&orderBy=sku&orderDir=ASC&destino_id[]=19
      $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
      $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
      $orderBy = isset($_GET['orderBy']) ? $this->conn->real_escape_string($_GET['orderBy']) : '1';
      $orderDir = isset($_GET['orderDir']) ? $this->conn->real_escape_string($_GET['orderDir']) : 'ASC';
      $query = $this->getSelectQuery(
        $page,
        $limit,
        $orderBy,
        $orderDir
      );
      // die($query);
      $result = $this->runQuery($query);
      $rows = $result->fetch_all(MYSQLI_ASSOC);
      return $rows;
    }

    protected function getFilterOptions() {
      $filtering = isset($_GET['filtering']) ? $this->conn->real_escape_string($_GET['filtering']) : null;
      if(!$filtering) {
        throw new \Exception("Filtering parameter is required for this request.");
      }

      $query = $this->getSelectQuery(1, 1, $filtering, 'ASC', false, false, $filtering);
      $result = $this->runQuery($query);
      $rows = $result->fetch_all(MYSQLI_ASSOC);
      return $rows;
    }

    protected function getTotalCount() {
      $query = $this->getTotalCountQuery();
      $result = $this->runQuery($query);
      $row = $result->fetch_assoc();
      return intval($row['total_count']??0);
    }

    public function run(){
      $microtime = microtime(true);
      $response = [
        'success' => true,
        'data' => []
      ];

      if(@$_GET['getMeta'] === "columns"){
        $response['data']['rows'] = $this->getColumnsMeta();
      } elseif(@$_GET['filtering']) {
        // Get rows
        $response['data']['rows'] = $this->getFilterOptions();
      } else {
        // Get total count
        $response['data']['totalRows'] = $this->getTotalCount();

        // Get totals if there are aggregate columns
        if($this->hasAggregateColumns()) {
          $totalsQuery = $this->getTotalsQuery();
          $result = $this->runQuery($totalsQuery);
          $totals = $result->fetch_assoc();
          $totals = array_map(function($value) {
            return is_numeric($value) ? floatval($value) : $value;
          }, $totals??[]);
          $response['data']['totals'] = $totals;
        }
        // Get rows
        $response['data']['rows'] = $this->getRows();
      }

      $microtime = microtime(true) - $microtime;
      $secondsWithTwoDecimal = round($microtime, 2);
      $response['data']['executionTime'] = $secondsWithTwoDecimal;
      echo json_encode($response);
      exit;
        
    }
  }
}