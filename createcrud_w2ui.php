<?php

/*
 * ZF2-w2ui-CRUD-generator (c) Darius Kilinskis, kilinskis@gmail.com
 *
 * @link      http://github.com/githabas/ZF2-w2ui-CRUD-generator
 */

/************************************************
* 
* == DESCRIPTION == 
*   - creates and/or updates Zend Framework 2 module, controller, model, view, configs files and folders
*   - generated tree uses W2UI (http://w2ui.com) Javascript library instead of ZF2 grids and forms
* 
* == TO START USING == 
*   - install ZF2 skeleton-application as described in http://framework.zend.com/manual/2.0/en/user-guide/skeleton-application.html
*   - include w2ui-1.4.js and w2ui-1.4.css in your application layout.phtml file 
* 
* == USAGE == 
* 	To generate CRUD for table 'apples':
*   - create MySQL table 'apples' with at least 3 columns
*	- place this file in your applications root folder
*	- in console type
* 		php createcrud_w2ui.php food apples
*
************************************************/

	
//---- CRUD form header messages
$msg_add = 'New record';
$msg_edit = 'Edit record';  


//---- parsing module name, controller name, dir name from command line arguments
if (isset($argv[1])) {
	$Module = ucwords($argv[1]);
	if (isset($argv[2])) {
		$tableName = ucwords(preg_replace_callback('/_([a-z])/i', function($matches) { return ucwords($matches[1]);	}, $argv[2]));
		$tableDir = str_replace('_', '-', $argv[2]);
	} else {
		die("Controller not defined.\n");
	}
} else {
	die("Module name not defined.\n");
}


//---- generated files array; set "true" to "false" in case you don't want that file to be generated
$filesArray = array(
	"Module" 			 => array(true, "module/$Module/Module.php"), // 0
	"autoload_classmap"  => array(true, "module/$Module/autoload_classmap.php"), // 1
	"module.config" 	 => array(true, "module/$Module/config/module.config.php"),  // 2
	"Controller" 		 => array(true, "module/$Module/src/$Module/Controller/$tableName"."Controller.php"), // 3
	"index" 			 => array(true, "module/$Module/view/$argv[1]/$tableDir/index.phtml"), // 4
	"application.config" => array(true, "config/application.config.php"), // 8
	"Model" 			 => array(true, "module/$Module/src/$Module/Model/$tableName.php"), // 9
);


//---- getting column names
$config = array_merge_recursive(require_once 'config/autoload/global.php', require_once 'config/autoload/local.php');

$parts = explode(';', $config['db']['dsn']);
$dbparts = explode('=', $parts[0]);
$hostparts = explode('=', $parts[1]);

$dbname   = $dbparts[1];
$host     = $hostparts[1];
$username = $config['db']['username'];
$password = $config['db']['password'];

$db = mysqli_connect($host, $username, $password, $dbname) or die("Error " . mysqli_error($db));

$sql = "SHOW COLUMNS FROM `$argv[2]`";
if(!$result = $db->query($sql)){
	die("There was an error running the query $sql ". $db->error);
} else {
	while($row = $result->fetch_assoc()){
		$columns[] = $row['Field'];
	}
}


//---- creating folders
createDir("module/$Module");
createDir("module/$Module/config");
createDir("module/$Module/src");
createDir("module/$Module/src/$Module");
createDir("module/$Module/src/$Module/Controller");
createDir("module/$Module/src/$Module/Form");
createDir("module/$Module/src/$Module/Model");
createDir("module/$Module/view");
createDir("module/$Module/view/$argv[1]");
createDir("module/$Module/view/$argv[1]/$tableDir");

echo "tree ok\n";


//---- Module
$fileName = $filesArray['Module'][1];
if (file_exists($fileName) && $filesArray['Module'][0] === false) {
	echo "File exist $fileName\n";
} else {
	if (file_exists($fileName)) {
		$handle = fopen($fileName, "r");
		$tmp_file = "tmp.php";
		if (file_exists($tmp_file)) {
			unlink($tmp_file);
		}
		$added = false;
		$exist = false;
			
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				if(strpos($line, "$Module\Model\\$tableName")) {
					$exist = true;
				}
				if ($added === false && $exist === false && strpos($line, 'Table;')) {
					file_put_contents($tmp_file, "use $Module\Model\\$tableName".";\n", FILE_APPEND);
	        		$added = true;
				}
				file_put_contents($tmp_file, $line, FILE_APPEND);
			}
		} else {
    		echo "error opening $fileName.\n";
		} 
		fclose($handle);
		if (file_exists($fileName)) {
			unlink($fileName);
			rename($tmp_file, $fileName);
		}
		echo "Updated $fileName\n";
	} else {
	ob_start();
	echo '<?php'."\n";
?>

namespace <?php echo $Module; ?>;

use <?php echo $Module; ?>\Model\<?php echo $tableName; ?>;
// use <?php echo $Module; ?>\Model\<?php echo $tableName; ?>Table;
use Zend\Db\ResultSet\ResultSet;
// use Zend\Db\TableGateway\TableGateway;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    
    public function getServiceConfig()
    {
        return array(
            'factories' => array(
/*                '<?php echo $Module; ?>\Model\<?php echo $tableName; ?>Table' =>  function($sm) {
                    $tableGateway = $sm->get('<?php echo $tableName; ?>TableGateway');
                    $table = new <?php echo $tableName; ?>Table($tableGateway);
                    return $table;
                },
                '<?php echo $tableName; ?>TableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new <?php echo $Module; ?>());
                    return new TableGateway('<?php echo $argv[2]; ?>', $dbAdapter, null, $resultSetPrototype);
                }, 
            ), */
        );
    }
} 
 
<?php
	ob_end($fileName);
	}
}


//---- autoload_classmap
$fileName = $filesArray['autoload_classmap'][1];
if (file_exists($fileName) && $filesArray['autoload_classmap'][0] === false) {
	echo "File exist $fileName\n";
} else {
	ob_start();
	echo '<?php'."\n";
	echo 'return array();'."\n";
	ob_end($fileName);	
}

		
//---- module.config
$fileName = $filesArray['module.config'][1];
if (file_exists($fileName) && $filesArray['module.config'][0] === false) {
	echo "File exist $fileName\n";
} else {
	if (file_exists($fileName)) {
		$handle = fopen($fileName, "r");
		$tmp_file = "tmp.php";
		if (file_exists($tmp_file)) {
			unlink($tmp_file);
		}
		$array1_begin = false;
		$array1_end = false;
		$exists = false;
	
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				if (trim($line) == "'invokables' => array(") {
	        		$array1_begin = true;
				}
				if (strpos($line, "$Module\Controller\\$tableName"."Controller")) {
					$exists = true;
				}
				if (trim($line) == ")," && $array1_begin === true && $array1_end === false && $exists = false) {
					file_put_contents($tmp_file, "			'$Module\Controller\\$tableName' => '$Module\Controller\\$tableName"."Controller',\n", FILE_APPEND);
					$array1_end = true;
				}
				file_put_contents($tmp_file, $line, FILE_APPEND);
//				$pline = $line;
			}
		} else {
    		echo "error opening $fileName.\n";
		} 
		fclose($handle);
		if (file_exists($fileName)) {
			unlink($fileName);
			rename($tmp_file, $fileName);
		}
		echo "Updated $fileName\n";
	} else {
	ob_start();
	echo '<?php'."\n";
?>

return array(
	'controllers' => array(
		'invokables' => array(
			'<?php echo $Module; ?>\Controller\<?php echo $tableName; ?>' => '<?php echo $Module; ?>\Controller\<?php echo $tableName; ?>Controller',
		),
	),

    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
            '<?php echo $argv[1]; ?>' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/<?php echo $argv[1]; ?>',
                    'defaults' => array(
                        '__NAMESPACE__' => '<?php echo $Module; ?>\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
							'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
    

	'view_manager' => array(
		'template_path_stack' => array(
			'<?php echo $argv[1]; ?>' => __DIR__ . '/../view',
		),
	),
);

<?php
	ob_end($fileName);
	}
}


//---- Controller
$fileName = $filesArray['Controller'][1];
if (file_exists($fileName) && $filesArray['Controller'][0] === false) {
	echo "File exist $fileName\n";
} else {
   	ob_start();
	echo '<?php'."\n";
?>

namespace <?php echo $Module; ?>\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Sql;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;

class <?php echo $tableName; ?>Controller extends AbstractActionController
{
    public function indexAction()
    {
		return new ViewModel(array(
            'lang' => 'lt',
        ));    	
    }

    public function dispatchAction()
	{
		$request = $this->getRequest();
		$source = $request->getPost('source', '');
		$cmd = $request->getPost('cmd', '');
		$recid = $request->getPost('recid', 0);
		$where = $request->getPost('where', array());
		
		switch ($source."::".$cmd) {

			case '::get-records':
				$limit = $request->getPost('limit', 20);
				$offset = $request->getPost('offset', 0);
				$sort = $request->getPost('sort', '');
				$search = $request->getPost('search', '');
				$dataset = $this->_getdata($where, $limit, $offset, $sort, $search);
			break;

			case '::delete-records':
				$selected = $request->getPost('selected', array());
				$dataset = $this->_delete($selected, $where);
			break;
				
		    case 'form::get-record':
				$dataset = $this->_getrecord($recid, $where);
			break;

			case 'form::save-record':
				$record = $request->getPost('record', array());
				$dataset = $this->_saverecord($recid, $record, $where);
			break;

		default:
			$dataset = array(
			    'status' => "error",
				'message' => 'Command "'.$cmd.'" is not recognized.'
			);
        	break;
		}

		$this->response->setContent(json_encode($dataset));
		return $this->response;
		
	}


    private function _getdata($where, $limit, $offset, $sort, $search)
	{
		
		$sm = $this->getServiceLocator();
		$adapter = $sm->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($adapter);
		
//		$lang = $where['lang'];
		
		$select = $sql->select();
		$select->from(array('<?php echo $argv[2]; ?>' => '<?php echo $argv[2]; ?>'));
		$select->columns(array('recid' => '<?php echo $columns[0]; ?>', '<?php echo $argv[2]; ?>-<?php echo $columns[0]; ?>' => '<?php echo $columns[0]; ?>', '<?php echo $argv[2]; ?>-<?php echo $columns[1]; ?>' => '<?php echo $columns[1]; ?>'));
//		$select->join(
//     		array('channels_lang' => 'channels_lang'), // table name
//     		'channels_lang.id = <?php echo $argv[2]; ?>.id', // expression to join on (will be quoted by platform object before insertion),
//     		array('channels_lang.lang', 'channels_lang.name'), // (optional) list of columns, same requirements as columns() above
//     		$select::JOIN_LEFT // (optional), one of inner, outer, left, right also represented by constants in the API
//		);		
//		$select->where("<?php echo $argv[2]; ?>.lang = '$lang'");
		//--- order
		if (is_array($sort)) {
			foreach ($sort as $key => $value) {
				$field = str_replace('-', '.', $value['field']);
				$select->order($field.' '.$value['direction']);
			}
		}
		//--- search
		if (is_array($search)) {
			foreach ($search as $key => $value) {
				$select->where->like($value['field'], '%'.$value['value'].'%');
			}
		}
		
		$select->quantifier(new Expression('SQL_CALC_FOUND_ROWS'));
		$select->limit($limit); 
		$select->offset($offset);

		$selectString = $sql->getSqlStringForSqlObject($select);
		$executed = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE);
		$results = new ResultSet();
		$executed_array = $results->initialize($executed)->toArray();

		$statement = $adapter->query('SELECT FOUND_ROWS()');
		$results = $statement->execute();
		$row = $results->current();
		$found_rows = $row['FOUND_ROWS()'];

		$dataset = array(
		    'status' => "success",
			'total'	=>	$found_rows,
			'records' => $executed_array
		);
		return $dataset;
	}

	private function _getrecord($recid, $where)
	{
		$sm = $this->getServiceLocator();
		$adapter = $sm->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($adapter);

//		$lang = $where['lang'];
		
		$select = $sql->select();
		$select->from(array('C' => '<?php echo $argv[2]; ?>'));
		$select->columns(array('recid' => '<?php echo $columns[0]; ?>', '<?php echo $argv[2]; ?>-<?php echo $columns[0]; ?>' => '<?php echo $columns[0]; ?>', '<?php echo $argv[2]; ?>-<?php echo $columns[1]; ?>' => '<?php echo $columns[1]; ?>'));
//		$select->join(
//     		array('CL' => 'channels_lang'), // table name
//     		'CL.id = C.id', // expression to join on (will be quoted by platform object before insertion),
//     		array('channels_lang-lang' => 'lang', 'channels_lang-name' => 'name'), // (optional) list of columns, same requirements as columns() above
//     		$select::JOIN_LEFT // (optional), one of inner, outer, left, right also represented by constants in the API
//		);		
		$select->where("C.<?php echo $columns[0]; ?> = '$recid'");
//		$select->where("CL.lang = '$lang'");
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$statement = $adapter->query($selectString);
		$results = $statement->execute();
		$row = $results->current();

		if($row !== false) {
			$status = "success"; 
		} else {
			$status = "error"; 
		}
		
		$dataset = array(
	  		'status' => $status,
			'record' => $row
		);
		return $dataset;
	}

	private function _saverecord($recid, $record, $where)
	{
		$sm = $this->getServiceLocator();
		$adapter = $sm->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($adapter);
		
		$auth = $sm->get('Zend\Authentication\AuthenticationService');
		$usr = $auth->getIdentity();
		$modifier = $usr->id;
		
		$tables = array();
		foreach ($record as $key => $value) {
			$parts = explode('-', $key);
			if(isset($parts[1])) {
				if (is_array($value)) {  //-- jei tatai 'list' laukelis
					$tables[$parts[0]][$parts[1]] = $value['id'];
				} else {
					$tables[$parts[0]][$parts[1]] = $value;
				}
			}			
		}
		$wheres = array();
		foreach ($where as $key => $value) {
			$parts = explode('-', $key);
			$vparts = explode('-', $value);
			if(isset($parts[1]) && isset($vparts[1])) {
				$wheres[$parts[0]][$parts[1]] = $tables[$vparts[0]][$vparts[1]];
			}			
		}
		
// to do -- $tables validate
		$affectedRows = 0;
		if ($recid) { //-- update
			$tables['<?php echo $argv[2]; ?>']['modifier'] = $modifier;
			$wheres['<?php echo $argv[2]; ?>']['<?php echo $columns[0]; ?>'] = $recid;
			foreach ($tables as $table => $set) {
				$update = $sql->update($table);
				$update->set($set);
				foreach ($wheres[$table] as $where => $value) {
					$update->where("$where = '$value'");
				}
				$query = $sql->getSqlStringForSqlObject($update);
				$statement = $adapter->query($query);
				$result = $statement->execute();
				$affectedRows += $result->count();
			}
		} else {  //-- insert
			foreach ($tables as $key => $value) {
				$insert = $sql->insert($key);
				$insert->values($value);
				$query = $sql->getSqlStringForSqlObject($insert);
				$statement = $adapter->query($query);
				$result = $statement->execute();
				$affectedRows += $result->count();
			}
		}
		
		if(is_numeric($affectedRows)) {
			$status = "success"; 
		} else {
			$status = "error"; 
		}
		
		$dataset = array(
	  		'status' => $status,
			'message' => 'affectedRows: '.$affectedRows
		);
		return $dataset;
	}

	private function _delete($selected, $where)
	{
		$sm = $this->getServiceLocator();
		$adapter = $sm->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($adapter);
		$affectedRows = 0;
		
		$delete = $sql->delete('<?php echo $argv[2]; ?>');
		$delete->where(array(
    		'<?php echo $columns[0]; ?>' => $selected,
		));		
		$query = $sql->getSqlStringForSqlObject($delete);
		$statement = $adapter->query($query);
		$result = $statement->execute();
		$affectedRows += $result->count();

//		$delete = $sql->delete('channels_lang');
//		$delete->where(array(
//    		'id' => $selected,
//		));		
//		$query = $sql->getSqlStringForSqlObject($delete);
//		$statement = $adapter->query($query);
//		$result = $statement->execute();
//		$affectedRows += $result->count();

		if(is_numeric($affectedRows)) {
			$status = "success"; 
		} else {
			$status = "error"; 
		}
		
		$dataset = array(
	  		'status' => $status,
			'message' => 'affectedRows: '.$affectedRows
		);
		return $dataset;
	}

}

<?php
	ob_end($fileName);	
}


//---- index
$fileName = $filesArray['index'][1];
if (file_exists($fileName) && $filesArray['index'][0] === false) {
	echo "File exist $fileName\n";
} else {
   	ob_start();
	echo '<?php'."\n";
?>

$title = '<?php echo $tableName; ?>';
$this->headTitle($title);
<?php echo "?>\n"; ?>
<h1><?php echo '<?php echo $this->escapeHtml($title); ?>'; ?></h1>

<div id="myGrid" style="height: 450px"></div>

<script>

$(function () {

	$('#myGrid').w2grid({ 
		name: 'myGrid',
		multiSearch: false,
		url : '<?php echo '<?php echo $this->url('."'$argv[1]/default'".','." array('controller'=>'$argv[2]','action'=>'dispatch'));?>"; ?>',
		limit: 30,
		show: {
			toolbar: true,
			footer: true,
			toolbarAdd: true,
			toolbarDelete: true,
			toolbarEdit: true
		},
		searches: [
			{ field: '<?php echo $argv[2]; ?>-<?php echo $columns[0]; ?>', caption: '<?php echo $columns[0]; ?>', type: 'int' },
			{ field: '<?php echo $argv[2]; ?>-<?php echo $columns[1]; ?>', caption: '<?php echo $columns[1]; ?>', type: 'text' },
			{ field: '<?php echo $argv[2]; ?>-<?php echo $columns[2]; ?>', caption: '<?php echo $columns[2]; ?>', type: 'text' },
		],	
		columns: [				
			{ field: '<?php echo $argv[2]; ?>-<?php echo $columns[0]; ?>', caption: '<?php echo $columns[0]; ?>', size: '20%', sortable: true  },
			{ field: '<?php echo $argv[2]; ?>-<?php echo $columns[1]; ?>', caption: '<?php echo $columns[1]; ?>', size: '40%', sortable: true  },
			{ field: '<?php echo $argv[2]; ?>-<?php echo $columns[2]; ?>', caption: '<?php echo $columns[2]; ?>', size: '40%', sortable: true  },
		],
/*		postData: {
			where : {
				module_id : selected_module,
				lang : 'lt'
			}, 
		}, 
		onRequest: function(event) {
			event.postData.where.module_id = selected_module;
		},	*/	
		onAdd: function (event) {
			editForm(0);
		},
		
		onEdit: function (event) {
			editForm(event.recid);
		},
	
		onDblClick: function (event) {
			editForm(event.recid);
		},
		
	});
	
	$().w2form({
		name: 'edit_form',
		style: 'border: 0px; background-color: transparent;',
		url : '<?php echo '<?php echo $this->url('."'$argv[1]/default'".','." array('controller'=>'$argv[2]', 'action'=>'dispatch'));?>"; ?>',
		fields: [
			{ name: '<?php echo $argv[2]; ?>-<?php echo $columns[0]; ?>', type: 'text', required: true, html: { caption: '<?php echo $columns[0]; ?>', attr: 'size="40" maxlength="40"' } },
			{ name: '<?php echo $argv[2]; ?>-<?php echo $columns[1]; ?>', type: 'text', required: true, html: { caption: '<?php echo $columns[1]; ?>', attr: 'size="40" maxlength="40"' } },
			{ name: '<?php echo $argv[2]; ?>-<?php echo $columns[2]; ?>', type: 'text', required: true, html: { caption: '<?php echo $columns[2]; ?>', attr: 'size="40" maxlength="40"' } },
		],

        actions: {
            "Save": function () { 
                this.save(function (data) {
                    if (data.status == 'success') {
                        w2ui['myGrid'].reload();
                        $().w2popup('close');
                    }
                }); 
            },
            "Cancel": function () { 
                $().w2popup('close');
            },
        },
        
	});
	    
	function editForm(recid) {
    	$().w2popup('open', {
	        title   : (recid == 0 ? '<?php echo $msg_add; ?>' : '<?php echo $msg_edit; ?>'),
        	body    : '<div id="form" style="width: 100%; height: 100%"></div>',
        	style   : 'padding: 15px 0px 0px 0px',
			opacity	: 0.1,
        	width   : 500,
        	height  : 300, 
        	onOpen  : function (event) {
	            event.onComplete = function () {
					w2ui['edit_form'].clear();
					w2ui['edit_form'].recid = recid;
					var where = {};
					w2ui['edit_form'].postData = {
						source : 'form',
					//	where : {
					//		lang : 'lt',
					//		"<?php echo $argv[1]; ?>-id" : '<?php echo $argv[1]; ?>-id',
					//	}, 
					};
					$('#w2ui-popup #form').w2render('edit_form');
            	}
        	}
    	});
	}	

});

</script>

<?php
	ob_end($fileName);
}


//---- application.config
$file = $filesArray['application.config'][1];
if (file_exists($file) && $filesArray['application.config'][0] !== false) {
	$handle = fopen($file, "r");
	$tmp_file = "config/tmp.php";
	if (file_exists($tmp_file)) {
		unlink($tmp_file);
	}
	$array_begin = false;
	$updated = false;
	$exists = false;
	
	if ($handle) {
		while (($line = fgets($handle)) !== false) {
			if (trim($line) == "'modules' => array(") {
	        	$array_begin = true;
			}
			if (strpos($line, $Module)) {
				$exists = true;
			}
			if (trim($line) == ")," && $array_begin === true && $updated === false && $exists === false) {
				file_put_contents($tmp_file, "		'$Module',\n", FILE_APPEND);
				$updated = true;
			}
			file_put_contents($tmp_file, $line, FILE_APPEND);
		}
	} else {
    	echo "error opening config/application.config.php.\n";
	} 
	fclose($handle);
	if (file_exists($file)) {
		unlink($file);
		rename($tmp_file, $file);
	}
	echo "Updated config/application.config.php\n";
}
	
	if (!empty($columns)) {
		
//---- Model
$fileName = $filesArray['Model'][1];
if (file_exists($fileName) && $filesArray['Model'][0] === false) {
	echo "File exist $fileName\n";
} else {
		ob_start();
		echo '<?php'."\n";
?>
namespace <?php echo $Module; ?>\Model;

use Zend\InputFilter\Factory as InputFactory;
use Zend\InputFilter\InputFilter;            
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;

class <?php echo $tableName; ?> implements InputFilterAwareInterface
{
<?php
	foreach ($columns as $key => $value) {
		echo '	public $'.$value.";\n";		
	}
	echo '	protected $inputFilter;'."\n";
	echo "\n";
	echo '	public function exchangeArray($data)'."\n";
	echo "	{\n";
	foreach ($columns as $key => $value) {
		echo '		$this->'.$value.' = (isset($data['."'$value'".'])) ? $data['."'$value'".'] : null;'."\n";		
	}
	echo "	}\n";
	echo "\n";
?>

    public function getArrayCopy()
    {
        return get_object_vars($this);
    }

    public function setInputFilter(InputFilterInterface $inputFilter)
    {
        throw new \Exception("Not used");
    }

    public function getInputFilter()
    {
        if (!$this->inputFilter) {
            $inputFilter = new InputFilter();
            $factory     = new InputFactory();

            $inputFilter->add($factory->createInput(array(
                'name'     => '<?php echo $columns[0]; ?>',
                'required' => true,
                'filters'  => array(
                    array('name' => 'Int'),
                ),
            )));

            $inputFilter->add($factory->createInput(array(
                'name'     => '<?php echo $columns[1]; ?>',
                'required' => true,
                'filters'  => array(
                    array('name' => 'StripTags'),
                    array('name' => 'StringTrim'),
                ),
                'validators' => array(
                    array(
                        'name'    => 'StringLength',
                        'options' => array(
                            'encoding' => 'UTF-8',
                            'min'      => 1,
                            'max'      => 255,
                        ),
                    ),
                ),
            )));

            $this->inputFilter = $inputFilter;
        }

        return $this->inputFilter;
    }
<?php
	echo "}\n";
?>

<?php
	ob_end($fileName);	
}		
	} else {
    	echo "No columns in table $argv[2]\n";
	} 


function createDir($path) {
	if (!file_exists($path)) {
   		mkdir($path, 0755);
		echo "Dir created $path\n";
	} else {
		echo "Dir exists $path\n";
	}		
}

function ob_end($fileName) {
	$htmlStr = ob_get_contents();
	ob_end_clean(); 
	file_put_contents($fileName, $htmlStr);	
	echo "Written $fileName\n";
}	

?>
	
