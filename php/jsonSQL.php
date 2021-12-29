<?php

class jsonSQL {

    function __construct() {

		parent::__construct($_GET['db']);

        $_GET['query'] = json_decode($_GET['query'], true);

        // ------------------------------------------------
        
        $page = 0;
        if (isset($_GET['query']['offset']) && !empty($query['offset'])) {
        $page = filter_var($_GET['query']['offset'], FILTER_SANITIZE_NUMBER_INT);
        }
        $per_page = 20;
        if (isset($_GET['query']['limit']) && !empty($_GET['query']['limit'])) {
            $per_page = filter_var($_GET['query']['limit'], FILTER_SANITIZE_NUMBER_INT);
        }

        $params = [];
        $sqlcount = "SELECT count(*) AS total_records FROM {$_GET['query']['0']['select']['from']['table']}";

        if(isset($_GET['query']['1']['where'])) {
            $sqlcount .= " WHERE ";
            for($i = 0; $i < count($_GET['query']['1']['where']); $i++) {
				if($i !== 0) {
					$sqlcount .= " AND ";
				}
				foreach($_GET['query']['1']['where'][$i] as $key => $value) {
					switch($key) {
						case "identifier":
							$sqlcount .= $value;
							break;
						case "between":
							$sqlcount .= " BETWEEN ";
							for($j = 0; $j < count($value); $j++) {
								if($j == 0) {
									$params['where_'.$i.'_between_'.$j.'_value'] = $value[$j];
									$sqlcount .= ":where_{$i}_between_{$j}_value";
								} else {
									$params['where_'.$i.'_between_'.$j.'_value'] = $value[$j];
									$sqlcount .= " AND :where_{$i}_between_{$j}_value";
								}
							}
							break;
						case "value":
							$params['where_'.$i.'_value'] = $_GET['query']['1']['where'][$i][$key];
							$sqlcount .= "=:where_{$i}_value";
							break;
					}	
				}
            }
        }

        $stmt = $this->pdo->prepare($sqlcount);
        $stmt->execute($params);
        $row = $stmt->fetch();
        $total_records = $row['total_records'];
    
        $total_pages = ceil($total_records / $per_page);
    
        $offset = ($page) * $per_page;
        
        // ------------------------------------------------
        
        $this->db = json_decode($_GET['db'], true);
        $this->table = "";
        $this->columns = "";
        $this->query = $_GET['query'];
		$this->params = [];
		$this->sql = "";

		$statements = $this->query;

		$this->pcount = 0;

		foreach((array) $statements as $statement) {
			$this->pcount++;
			switch(key($statement)) {
				case "select":
					$this->select($statement["select"]);
					break;				
				case "where":
					$this->where($statement["where"]);
					break;
				case "order_by":
					$this->order_by($statement["order_by"]);
					break;
				case "inner_join":
					$this->inner_join($statement["inner_join"]);
					break;
				case "left_join":
					$this->left_outer_join($statement["left_join"]);
					break;
				case "left_outer_join":
					$this->left_outer_join($statement["left_outer_join"]);
					break;
				case "limit":
					$this->limit($statement["limit"]);
					break;
				case "offset":
					$this->offset($statement["offset"]);
					break;
				default:
					$error = key($statement);
					$this->response = array("type"=>"error", "status"=>"400", "message"=>"Bad Request", "data"=>"Illegal query statement: {$error}");
					header('Content-Type: application/json');
					echo json_encode($this->response);
					exit;
			}
		}

        // ------------------------------------------------

        $stmt = $this->pdo->prepare($this->sql);
        $stmt->execute($this->params);

        $result = [];
        //$result = $stmt->fetchAll();
        $i = 0;
        while ( ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) !== false) {
            $result[] = $row;
            $i++;
        }
        $data = [];
        $data["db"] = $this->db;
        $data["table"] = $this->table;
        $data["columns"] = $this->columns;
        $data["query"] = $this->sql;
        $data["dataset"] = $result;
        $data["records"] = $i;
        $data["totalrecords"] = $total_records;
        $result["sql"] = $this->sql;
        $result["params"] = $this->params;
        $this->response = array("type"=>"success", "status"=>"200", "statusText"=>"OK", "data"=>$data, "message"=>"Retrieved all data successfully");
    }

    private function operators($statement) {
		switch($key) {
			case "and":
				break;
			case "or":
				break;
			case "not":
				break;
			case "and not":
				break;
			case "or not":
				break;
			default:
				exit;
		}
	}

	private function select($statement) {
		$this->sql = "SELECT ";
		foreach((array) $statement as $key => $value) {
			switch($key) {
				case "columns":
					for($i = 0; $i < count($value); $i++) {
						if($i == 0) {
							$this->sql .= "{$value[$i]}";
                            $this->columns .= "{$value[$i]}";
						} else {
							$this->sql .= ", {$value[$i]}";
                            $this->columns .= ",{$value[$i]}";
						}
					}
					break;
				case "from":
					$this->sql .= " FROM {$value['table']}";
                    $this->table .= "{$value['table']}";
					($value['as'])? $this->sql .= " AS {$value['as']}": $this->sql .= "";
					break;
				default:
					$this->response = array("type"=>"error", "status"=>"400", "message"=>"Bad Request", "data"=>"Illegal query statement: {$item}");
					header('Content-Type: application/json');
					echo json_encode($this->response);
					exit;
			}
		}
	}

	private function where($statement) {
		$this->sql .= " WHERE (";
		for($i = 0; $i < count($statement); $i++) {
			foreach((array) $statement[$i] as $key => $value) {
				switch($key) {
					case "operator":
						switch($value) {
							case "and":
								$this->sql .= ") AND (";
								break;
							case "or":
								$this->sql .= ") OR (";
								break;
							case "not":
								$this->sql .= ") NOT (";
								break;
						}
						break;
					case "like":
						$this->sql .= " LIKE :where_{$i}_like_value";
						$p['where_'.$i.'_like_value'] = $value;
						$this->params = $this->params + $p;
						break;
					case "identifier":
						if($i != 0) { $this->sql .= " AND "; }
						$this->sql .= "( " . $value;
						break;
					case "between":
						$this->sql .= " BETWEEN ";
						for($j = 0; $j < count($value); $j++) {
							if($j == 0) {
								$p['where_'.$i.'_between_'.$j.'_value'] = $value[$j];
								$this->params = $this->params + $p;
								$this->sql .= ":where_{$i}_between_{$j}_value";
							} else {
								$p['where_'.$i.'_between_'.$j.'_value'] = $value[$j];
								$this->params = $this->params + $p;
								$this->sql .= " AND :where_{$i}_between_{$j}_value )";
							}
						}
						break;
					case "value":
						$p['where_'.$i.'_value'] = $value;
						$this->params = $this->params + $p;
						$this->sql .= "=:where_{$i}_value)";
						break;
					default:
						$this->response = array("type"=>"error", "status"=>"400", "message"=>"Bad Request", "data"=>"Illegal query statement: {$item}");
						header('Content-Type: application/json');
						echo json_encode($this->response);
						exit;	
				}	
			}
		}
		$this->sql .= ")";
	}

	private function inner_join($statement) {
		$this->sql .= " INNER JOIN ";
		foreach((array) $statement as $key => $value) {
			switch($key) {
				case "table":
					$this->sql .= "{$statement['table']}";
					break;
				case "as":
					$this->sql .= " AS {$statement['as']}";
					break;
				case "on":
					$this->sql .= " ON {$value['identifier']} = {$value['value']}";
					// for($i = 0; $i < count($value); $i++) {
					// 	if($i == 0) {
					// 		// $p[$this->pcount.'_left_outer_join_on_'.$i.'_value'] = $value[$i];
					// 		// $this->params = $this->params + $p;
					// 		// $this->sql .= ":{$this->pcount}_left_outer_join_on_{$i}_value";
					// 		$this->sql .= "{$value[$i]}";
					// 	} else {
					// 		// $p[$this->pcount.'_left_outer_join_on_'.$i.'_value'] = $value[$i];
					// 		// $this->params = $this->params + $p;
					// 		// $this->sql .= " AND :{$this->pcount}_left_outer_join_on_{$i}_value";
					// 		$this->sql .= " AND {$value[$i]}";
					// 	}
					// }
					break;
			}
		}
	}

	private function left_outer_join($statement) {
		$this->sql .= " LEFT OUTER JOIN ";
		foreach((array) $statement as $key => $value) {
			switch($key) {
				case "table":
					$this->sql .= "{$statement['table']}";
					break;
				case "as":
					$this->sql .= " AS {$statement['as']}";
					break;
				case "on":
					$this->sql .= " ON ";
					for($i = 0; $i < count($value); $i++) {
						if($i == 0) {
							// $p[$this->pcount.'_left_outer_join_on_'.$i.'_value'] = $value[$i];
							// $this->params = $this->params + $p;
							// $this->sql .= ":{$this->pcount}_left_outer_join_on_{$i}_value";
							$this->sql .= "{$value[$i]}";
						} else {
							// $p[$this->pcount.'_left_outer_join_on_'.$i.'_value'] = $value[$i];
							// $this->params = $this->params + $p;
							// $this->sql .= " AND :{$this->pcount}_left_outer_join_on_{$i}_value";
							$this->sql .= " AND {$value[$i]}";
						}
					}
					break;
			}
		}
	}

	private function order_by($statement) {
		$this->sql .= " ORDER BY ";
		for($i = 0; $i < count($statement); $i++) {
			foreach((array) $statement[$i] as $key => $value) {
				switch($key) {
					case "identifier":
						if($i == 0) {
							$this->sql .= "{$value}";
						} else {
							$this->sql .= ", {$value}";
						}
						break;
					case "direction":
							$this->sql .= " {$value}";
						break;
				}
			}
		}
	}

	private function limit($statement) {
		$this->sql .= " LIMIT {$statement}";
	}

	private function offset($statement) {
		$this->sql .= " OFFSET {$statement}";
	}

}

return new jsonSQL();

?>