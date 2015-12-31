<?php

namespace Tools\Model;

use Zend\Db\Sql\Sql;
use Zend\Db\Sql\TableIdentifier;
use Zend\Db\Sql\Expression;

use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\Controller\AbstractRestfulController;

class StandartFuncs extends AbstractActionController
//class StandartFuncs
/************************************************
*
* == DESCRIPTION ==
*   - caries insert & update operations based on data retrieved from w2ui forms
*      - multiple table handling
* 	   - performs prime record on insert in child tables
*      - checks for duplicates on require unicue fields
*
************************************************/

{
	private $table_filter = array();

	public function setTableFilter($tables)
	{
		//-- tables to skip ()
		$this->table_filter = $tables;
	}

	public function getServiceConfig()
	{
		return array(
        	'factories' => array(
            	'funcservice' =>  function(\Zend\ServiceManager\ServiceLocatorInterface $sm) {
	                return $sm->get('Config');
				}
			)
		);
	}

	public function save($adapter, $recid, $record, $where, $original = array(), $lastid = array(), $remove = array(), $centais = array(), $modifier = array())
    {
/*     $adapter   - Zend db adapter
	 * $recid     - record ID in table
	 * $record    - array of values to save. [key: <table_name>-<field_name> value: <value>]
	 * $where     - where to save. key: <table_name>-<field_name> value: <key in $record array>
	 * $original  - array of original form values. To check for duplicates where duplicates not alowed.	Check performed if value in $original not equal to value in $record.
	 * 				key: <table_name_field_name>-<field_name> value: <value>
	 * $lastid    - "prime record on insert" array. key: <where to use table>-<where to use field> value: <source table>-<source field>
	 * 				! in $record's array "source" tables fields must be on top
	 * $remove    - array of fields to delete and insert on edit action. key: <table_name>-<field_name> value: source of field value in form <table_name>-<field_name>
	 * $centais   - array of fields to multiply by fixed values. key: <field_name> value: <fixed value>
	 * $modifier  - if it not empty, modifier and modified fields added to array of records
	 *
	 */
		$sql = new Sql($adapter);

		$global_config = new \Zend\Config\Config( include APPLICATION_PATH.'/config/autoload/global.php' );

		$config = array(
			'ACL_TABLES' => $global_config->ACL_TABLES->toArray(),
			'ACL_DB' => $global_config->ACL_DB
		);

		$tables = array();
		foreach ($record as $key => $value) {
			$parts = explode('-', $key);
			if(isset($parts[1])) {
				if (is_array($value)) {  //-- if it 'enum' or 'list' field
					if (isset($value[0]['id'])) {  //-- so it's 'enum'
						foreach ($value as $ekey => $evalue) {
							$tables[$parts[0]][$parts[1]][] = $evalue['id'];
						}
					} else {				//-- so it's 'list'
						$tables[$parts[0]][$parts[1]] = $value['id'];
					}
				} else {
					if (isset($centais[$parts[1]])) { //--- if in cents array
						(int)$value = $value * $centais[$parts[1]];
					}
					$tables[$parts[0]][$parts[1]] = $value;
				}
			}
		}

		if (!is_array($recid)) {
			$r = array();
			foreach ($tables as $key => $value) {
				$r[$key] = $recid;
			}
			$recid = $r;
		}

		$removes = array();
		foreach ($remove as $key => $value) {
			$parts = explode('-', $key);
			$vparts = explode('-', $value);
			if(isset($parts[1]) && isset($vparts[1])) {
				$removes[$parts[0]][$parts[1]] = $tables[$vparts[0]][$vparts[1]];
			}
		}

		$lastids = array();
		foreach ($lastid as $key => $value) {
			$parts = explode('-', $key);
			$vparts = explode('-', $value);
			if(isset($parts[0]) && isset($vparts[1])) {
				$lastids[$vparts[0]][$vparts[1]][$parts[0]] = $parts[1];
			}
		}
		//-- to ensure that 'lastids' table in tables array goes first
		if (!empty($lastids)) {
			$tmp = array();
			foreach ($lastids as $key => $value) {
				if (array_key_exists($key, $tables)) {
					$tmp[$key] = $tables[$key];
					unset($tables[$key]);
				}
			}
			foreach ($tables as $key => $value) {
				$tmp[$key] = $value;
			}
			$tables = $tmp;
		}

		$wheres_orig = array();
		foreach ($original as $key => $value) {
			$parts = explode('-', $key);
			if(isset($parts[0]) && isset($parts[1])) {
				if (is_array($value)) {  //-- if that's a field of type 'list'
					$value = $value['id'];
				}
				$wheres_orig[$parts[0]][$parts[1]] = $value;
			}
		}

		$wheres_new = array();
		foreach ($wheres_orig as $table => $array) {
			foreach ($array as $key => $value) {
				if (isset($tables[$table][$key])) {
					$wheres_new[$table][$key] = $tables[$table][$key];
				}
			}
		}

		$wheres = array();
		foreach ($where as $key => $value) {
			$parts = explode('-', $key);
			$vparts = explode('-', $value);
			if(isset($parts[0]) && isset($parts[1]) && isset($tables[$vparts[0]][$vparts[1]])) {
				$wheres[$parts[0]][$parts[1]] = $tables[$vparts[0]][$vparts[1]];
				if (!empty($recid[$key])) {
					unset($tables[$vparts[0]][$vparts[1]]);
				}
			}
		}

		$affectedRows = 0;
		$debug_mode = false;
//		$debug_mode = true;  //-- uncoment if you just want to see queries in console; no writes to db performed

		if ($debug_mode) {
			echo "recid:\n";
			print_r($recid);
			echo "tables:\n";
			print_r($tables);
			echo "wheres:\n";
			print_r($wheres);
			echo "removes:\n";
			print_r($removes);
			echo "wheres_orig:\n";
			print_r($wheres_orig);
			echo "wheres_new:\n";
			print_r($wheres_new);
			echo "lastids:\n";
			print_r($lastids);
//			exit;
		}

		$primeids = array();

		foreach ($tables as $table => $set) {
			if (!empty($this->table_filter)) {
				if(!isset($this->table_filter[$table])) {
					continue;
				}
			}
			if (in_array($table, $config['ACL_TABLES'])) {
				$table_ident = new TableIdentifier($table, $config['ACL_DB']);
			} else {
				$table_ident = $table;
			}
			//-- non standart modifier and modified field names handling
			if (!empty($modifier)) {
				if (is_array($modifier)) {
					if (isset($modifier[$table]['modifier_name'])) {
						$set[$modifier[$table]['modifier_name']] = $modifier[$table]['modifier'];
					}
					if (isset($modifier[$table]['modified_name'])) {
						$set[$modifier[$table]['modified_name']] = new Expression('NOW()');
					}
				} else {
					$set['modifier'] = $modifier;
					$set['modified'] = new Expression('NOW()');
				}
			}

			if(!$recid[$table] || isset($wheres_new[$table])) {  //-- so it is insert or edit with possibly changed required unicue fields
				if (isset($wheres_orig[$table]) && $wheres_orig[$table] != $wheres_new[$table]) {
					if ($this->duplicated($adapter, $table_ident, $wheres_new[$table])) {
						end($wheres_new[$table]);
						$dataset = array(
							'status' => 'success',
								'validate' => array(
								$table.'-'.key($wheres_new[$table]) => _('Record exists'),
							),
						);
						return $dataset;
					}
				}

				if ($recid[$table]) { //-- if that is edit anyway
					$dbobj = $sql->update($table_ident);
					$dbobj->set($set);
					foreach ($wheres[$table] as $where => $value) {
						if (isset($wheres_orig[$table][$where])) {
							$dbobj->where(array($where => $wheres_orig[$table][$where]));
						} else {
							$dbobj->where(array($where => $value));
						}
					}
				} else {  //-- if to insert
					$dbobj = $sql->insert($table_ident);
					if (isset($primeids[$table])) {
						$current = current($set);
						foreach ($primeids[$table] as $key => $value) {
							if (empty($value) && !empty($tables[$table][$key])) {
								$value = $tables[$table][$key];
							}
							if (is_array($current)) {
								foreach ($current as $cvalue) {
									$set = array(key($set) => $cvalue, $key => $value);
									$dbobj->values($set);
									$query = $sql->getSqlStringForSqlObject($dbobj);
									if (!$debug_mode) { $result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE); } else { echo "*1* $query\n"; }
								}
							} else {
								if(!array_filter($set)) {
									continue;
								}
								$set[$key] = $value;
								$dbobj->values($set);
								$query = $sql->getSqlStringForSqlObject($dbobj);
								if (!$debug_mode) { $result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE); } else { echo "*2* $query\n"; }
							}
						}
						continue;
					}
					$dbobj->values($set);
				}
				$query = $sql->getSqlStringForSqlObject($dbobj);
				if (!$debug_mode) {
					$result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE);
					$affectedRows += $result->count();
				} else {
					echo "*3* $query\n";
				}
				if (isset($lastids[$table])) {
					$lastId = $adapter->getDriver()->getLastGeneratedValue();
					foreach ($lastids[$table] as $key => $value) {
						foreach ($value as $key => $value) {
							$primeids[$key][$value] = $lastId;
						}
					}
				}
				if (!$debug_mode) {
					$affectedRows += $result->count();
				}
			} else {  //-- edit
				if (isset($removes[$table])) {    //-- if have to delete existing records
					$adapter->getDriver()->getConnection()->beginTransaction();

					$insert_set = array();
					$delete = $sql->delete($table_ident);
					foreach ($removes[$table] as $where => $value) {
						$insert_set[$where] = $value;
						$delete->where("$where = '$value'");
					}
					$query = $sql->getSqlStringForSqlObject($delete);
					if (!$debug_mode) {
						$result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE);
						$affectedRows += $result->count();
					} else {
						echo "*4* $query\n";
					}

					//-- so that's insert
					foreach ($tables[$table] as $key => $set) {
						if (empty($set)) {
							continue;
						}
						if(!is_array($set)) { //-- not an enum type field
							$dbobj = $sql->insert($table_ident);
							$dbobj->values(array_merge($tables[$table], $insert_set));
							$query = $sql->getSqlStringForSqlObject($dbobj);
							if (!$debug_mode) { $result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE); } else { echo "*5* $query\n"; }
							break;
						} else {
							foreach ($set as $ivalue) {
								$dbobj = $sql->insert($table_ident);
								$dbobj->values(array_merge(array($key => $ivalue), $insert_set));
								$query = $sql->getSqlStringForSqlObject($dbobj);
								if (!$debug_mode) { $result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE); } else { echo "*6* $query\n"; }
							}
						}
					}
					$adapter->getDriver()->getConnection()->commit();
					continue;
				}

				$update = $sql->update($table_ident);
				$update->set($set);
				foreach ($wheres[$table] as $where => $value) {
					$update->where(array($where => $value));
				}
				$query = $sql->getSqlStringForSqlObject($update);
				if (!$debug_mode) {
					$result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE);
					$affectedRows += $result->count();
				} else {
					echo "*7* $query\n";
				}
			}
		}
		$dataset = array(
			'status' => 'success',
			'message' => _('Affected rows: ').$affectedRows
		);
		return $dataset;
	}

    public function delete($adapter = array(), $table = '', $selected = array(), $where = '')
    {
		$sql = new Sql($adapter);
		$debug_mode = false;
//		$debug_mode = true;

		$delete = $sql->delete($table);
		if (is_array($where)) {
			$delete->where($where);
		} else {
			$delete->where(array($where => $selected));
		}
		$affectedRows = 0;
		$query = $sql->getSqlStringForSqlObject($delete);
		if (!$debug_mode) {
			$result = $adapter->query($query, $adapter::QUERY_MODE_EXECUTE);
			$affectedRows += $result->count();
		} else {
			echo "*8* $query\n";
		}

		if(is_numeric($affectedRows)) {
			$status = "success";
		} else {
			$status = "error";
			$affectedRows = print_r($affectedRows, true);
		}

		$dataset = array(
	  		'status' => $status,
			'message' => _('Affected rows: ').$affectedRows
		);
		return $dataset;
    }

    public function duplicated($adapter = array(), $table = '', $where = array())
    {
		$sql = new Sql($adapter);
		$select = $sql->select()
			->from($table)
		;
		foreach ($where as $key => $value) {
			$select->where(array($key => $value));
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$result = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE);
		return $result->current();
    }

    public function validate($controls = array())
    {
    	$validate_response = array();
		foreach ($controls as $key => $control) {
			switch ($control[2]) {
				case 'empty':
					if (empty($control[1])) {
						$validate_response[$control[0]] = $control[4];
					}
					break;
				case 'email':
					if (!filter_var($control[1], FILTER_VALIDATE_EMAIL)) {
						$validate_response[$control[0]] = $control[4];
					}
					break;
				case 'range':
					$parts = explode(':', $control[3]);
					if ($control[1] < $parts[0] || $control[1] > $parts[1]) {
						$validate_response[$control[0]] = $control[4];
					}
					break;
				case 'match':
					$parts = explode(':', $control[3]);
					if ($parts[0] != $parts[1]) {
						$validate_response[$control[0]] = $control[4];
					}
					break;
				default:
					break;
			}
		}
		return $validate_response;
	}

	public function strToPath($str, $locale = 'lt_LT.UTF8') {
		setlocale(LC_ALL, $locale);
		$str = strtolower(str_replace(' ', '-', preg_replace("/[^A-Za-z0-9 _\-]/", '', iconv("utf-8","ascii//TRANSLIT", $str))));
		return $str;
	}
}
