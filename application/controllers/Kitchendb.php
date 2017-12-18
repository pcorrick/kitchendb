<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
To do list:
1. [DONE 15-Dec-17] add a callback to delete rows from stockTrans table, if a batch is deleted
2. add a function to convert quantity units (g, kg, etc)
*/


class Kitchendb extends CI_Controller {

	public function __construct()
	{
		parent::__construct();

		$this->load->database();
		$this->load->helper('url');

		$this->load->library('grocery_CRUD');
	}

	public function _example_output($output = null)
	{
		$this->load->view('kitchendb.php',(array)$output);
	}

	public function index()
	{
		$this->_example_output((object)array('output' => '' , 'js_files' => array() , 'css_files' => array()));
	}

    public function ingredients()
        {
                try{
                        $crud = new grocery_CRUD();
                        $crud->set_theme('flexigrid');
                        $crud->set_table('ingredients');
                        $crud->set_subject('Ingredients');
                        $crud->columns(array('ingredientName'));
                        $crud->display_as('ingredientName','Ingredient');
                        $output = $crud->render();
                        $this->_example_output($output);
                }catch(Exception $e) {
                        show_error($e->getMessage().' --- '.$e->getTraceAsString());
                }
         }
    
    public function stock()
    {
        try{
                $crud = new grocery_CRUD();
                $crud->set_theme('flexigrid');
                $crud->set_model('Stock_model');
                $crud->set_table('stockTrans');
                $crud->set_subject('Stock');
                
                $crud->set_relation('stockTrans_ingredientID', 'ingredients', 'ingredientName');
                $crud->set_relation('stockTrans_batchID', 'batch', 'batch_batchCode');
                
                $where = "stockTrans_type = 'Created' group by stockTrans_batchID, stockTrans_ingredientID, stockTrans_type";
                $crud->where($where);
                
                // stockTrans_type field default = Created in mysql
                $crud->add_fields('stockTrans_ingredientID','stockTrans_quantity', 'stockTrans_quantityUOM');
                $crud->edit_fields('stockTrans_ingredientID','stockTrans_quantity');
                
                $crud->display_as('stockTrans_ingredientID','Ingredient');
                $crud->display_as('stockTrans_quantity','Quantity');
                $crud->display_as('stockTrans_batchID', 'Batch Code');
                
                $crud->required_fields('stockTrans_ingredientID','stockTrans_quantity', 'stockTrans_quantityUOM');
                
                $crud->columns('stockTrans_ingredientID','stockTrans_batchID', 'Created','Used', 'Current Stock');
                
                $crud->callback_column('Created', array($this, '_callback_created_column'));
                $crud->callback_column('Used', array($this, '_callback_used_column'));
                $crud->callback_column('Current Stock', array($this, '_callback_stock_column'));
                
                $output = $crud->render();
                $this->_example_output($output);
        }catch(Exception $e) {
                show_error($e->getMessage().' --- '.$e->getTraceAsString());
        }
     }
     
    public function _callback_stock_column($stockTransID, $row)
    {
        $created = $this->getQtyArray($stockTransID, $row, "Created");
        $used = $this->getQtyArray($stockTransID, $row, "Used");

        foreach ($created as $uom => $val) {
            if(array_key_exists($uom, $used) && array_key_exists($uom, $created))
                $stock[$uom] = $created[$uom] - $used[$uom];
        }
        return $this->formatUOM($stock);
    }
     
    public function formatUOM($arrayUOM) {
        $arrayUOM['kg'] = $arrayUOM['kg'] + ($arrayUOM['g']/1000);
        $arrayUOM['g'] = $arrayUOM['g'] % 1000;
        if($arrayUOM['kg'] < 1){
            return $arrayUOM['g'].' g';
        } else {
            return round($arrayUOM['kg'],3).' kg';
        }
    }

    public function getQtyArray($stockTransID, $row, $type) {
        $batchid = $row->stockTrans_batchID;
        $ingredientid = $row->stockTrans_ingredientID;
        
        if($type == "Created") {
            $query = $this->db->query("select sum(stockTrans_quantity) as stock, stockTrans_quantityUOM as qtyUOM from stockTrans where stockTrans_ingredientID = $ingredientid and stockTrans_type = '$type' and stockTrans_batchID = $batchid group by stockTrans_ingredientID, stockTrans_batchID, stockTrans_quantityUOM");
        } else {
            if($batchid == 0) { // if the row is a raw ingredient, it will not have a batchid 
                $query = $this->db->query("select sum(stockTrans_quantity) as stock, stockTrans_quantityUOM as qtyUOM from stockTrans where stockTrans_ingredientID = $ingredientid and stockTrans_type = '$type' group by stockTrans_ingredientID, stockTrans_quantityUOM");
            } else {
                $query = $this->db->query("select sum(stockTrans_quantity) as stock, stockTrans_quantityUOM as qtyUOM from stockTrans where stockTrans_ingredientID = $ingredientid and stockTrans_type = '$type' and stockTrans_subBatchID = $batchid group by stockTrans_ingredientID, stockTrans_quantityUOM, stockTrans_subBatchID");
            }
        }
        $queryrow = $query->row();
        $arrayUOM = array('g'=>0, 'kg'=>0);
        if(isset($queryrow)) {
            foreach($query->result() as $stockrow) {
                $arrayUOM[$stockrow->qtyUOM] = (float)$stockrow->stock;
            }
        }
        return $arrayUOM;
    }
    
    public function _callback_created_column($stockTransID, $row)
    {
        $type = "Created";
        $arrayUOM = $this->getQtyArray($stockTransID, $row, $type);
        return $this->formatUOM($arrayUOM);
    }

    public function _callback_used_column($stockTransID, $row)
    {
        $type = "Used";
        $arrayUOM = $this->getQtyArray($stockTransID, $row, $type);
        return $this->formatUOM($arrayUOM);
    }

    public function recipes()
    {
            try{
                    $crud = new grocery_CRUD();
                    $crud->set_theme('datatables');
                    $crud->set_table('recipes');
                    $crud->set_relation_n_n('ingredients', 'recipeItems', 'ingredients', 'recipeID', 'ingredientID', 'ingredientName');
                    $crud->set_relation('recipes_ingredientID','ingredients', 'ingredientName');
                    $crud->columns('productCode','recipeName','description','recipes_outputQuantity', 'recipes_outputUOM');
                    $crud->add_action('Edit Ingredients','','kitchendb/editingredients','ui-icon-plus');


                    $crud->display_as('productCode','Product Code');
                    $crud->display_as('recipeName','Recipe Name');
                    $crud->display_as('recipes_ingredientID','Ingredient Created');
                    $crud->display_as('recipes_outputQuantity','Quantity Created');
                    $crud->display_as('recipes_outputUOM', 'Units');
                    
                    $crud->required_fields('recipeName');
                    
                    $output = $crud->render();
                    $this->_example_output($output);
            }catch(Exception $e) {
                    show_error($e->getMessage().' --- '.$e->getTraceAsString());
            }
     }

     
    public function batch()
    {
            try{
                    $crud = new grocery_CRUD();
                    
                    $crud->set_theme('datatables');
                    $crud->set_table('batch');
                    
                    $crud->set_relation('batch_recipeID', 'recipes', 'recipeName');
                    $crud->columns('batch_batchCode', 'batch_recipeID','batch_cookDate','batch_quantity');
                    $crud->add_fields('batch_recipeID','batch_cookDate','batch_quantity');
                    $crud->edit_fields('batch_recipeID', 'batch_batchCode', 'batch_cookDate','batch_quantity');
                    
                    $crud->display_as('batch_batchCode','Batch Code');
                    $crud->display_as('batch_batchCode','Batch Code');
                    $crud->display_as('batch_recipeID','Recipe');
                    $crud->display_as('batch_cookDate','Date Made');
                    $crud->display_as('batch_quantity','Batch Quantity');
                    
                    $crud->required_fields('batch_recipeID', 'batch_quantity', 'batch_cookDate');
                    
                    $crud->add_action('Edit Batch','','kitchendb/editbatch','ui-icon-plus');
                                      
                    $crud->callback_after_insert(array($this, '_add_stock_transaction_callback'));
                    $crud->callback_after_delete(array($this, '_delete_batch_callback'));
                    $crud->callback_after_update(array($this, '_update_batch_callback'));

                    $output = $crud->render();
                    $this->_example_output($output);
                    
            }catch(Exception $e) {
                    show_error($e->getMessage().' --- '.$e->getTraceAsString());
            }
     }

     // callback after insert for the batch function
     function _add_stock_transaction_callback($post_array, $primary_key) {

            $recipeID = $post_array['batch_recipeID'];
            $batchQty = $post_array['batch_quantity'];
            
            // find the ingredientID column of the relevant recipe
            $query = $this->db->query("SELECT recipes_ingredientID, recipes_outputQuantity, recipes_outputUOM from recipes where recipes.id=$recipeID");
            $row = $query->row();
            $ingredientID = $row->recipes_ingredientID;
            $outputQty = $row->recipes_outputQuantity;
            $outputUOM = $row->recipes_outputUOM;
            
            //if the ingredientID is not null (i.e. this batch is an ingredient, so need to add it to stock)
            if(!(is_null($ingredientID))) {
                $qtyCreated = $outputQty * $batchQty;
                $queryStr = "INSERT into stockTrans (stockTrans_ingredientID, stockTrans_batchID, stockTrans_type, stockTrans_quantity, stockTrans_quantityUOM) VALUES ($ingredientID, $primary_key, 'Created', $qtyCreated, '$outputUOM')";
                $this->db->query($queryStr);
            }
            
            // add transactions for the ingredients that make up the batch
            $query = $this->db->query("SELECT ingredientID, quantity, recipeUnits from recipeItems where recipeID=$recipeID");
            
            foreach($query->result() as $row) {
                $ingredientID = $row->ingredientID;
                $qty = $batchQty * $row->quantity;
                $uom = $row->recipeUnits;
                $queryStr = "INSERT into stockTrans (stockTrans_ingredientID, stockTrans_batchID, stockTrans_type, stockTrans_quantity, stockTrans_quantityUOM) VALUES ($ingredientID, $primary_key, 'Used', $qty, $uom)";
                $this->db->query($queryStr);
            }
            
            $this->create_batch_code($primary_key, $post_array['batch_cookDate']);
            
            return $post_array;
    }

     // callback after update for the batch function
    function _update_batch_callback($post_array, $primary_key) {
        $recipeID = $post_array['batch_recipeID'];
        $batchQty = $post_array['batch_quantity'];
            
        // find the ingredientID column of the relevant recipe
        $query = $this->db->query("SELECT recipes_ingredientID, recipes_outputQuantity, recipes_outputUOM from recipes where recipes.id=$recipeID");
        $row = $query->row();
        $ingredientID = $row->recipes_ingredientID;
        $outputQty = $row->recipes_outputQuantity;
        $outputUOM = $row->recipes_outputUOM;
            
        //if the ingredientID is not null (i.e. this batch is an ingredient, so need to add it to stock)
        if(!(is_null($ingredientID))) {
            $qtyCreated = $outputQty * $batchQty;
            $queryStr = "UPDATE stockTrans SET stockTrans_quantity = $qtyCreated, stockTrans_quantityUOM = '$outputUOM' WHERE stockTrans_batchID = $primary_key and stockTrans_type = 'Created' and stockTrans_ingredientID = $ingredientID";
            $this->db->query($queryStr);
        }
            
        // add transactions for the ingredients that make up the batch
        $query = $this->db->query("SELECT ingredientID, quantity, recipeUnits from recipeItems where recipeID=$recipeID");
            
        foreach($query->result() as $row) {
            $ingredientID = $row->ingredientID;
            $qty = $batchQty * $row->quantity;
            $uom = $row->recipeUnits;
            $queryStr = "UPDATE stockTrans SET stockTrans_quantity = $qty, stockTrans_quantityUOM = '$uom' WHERE stockTrans_ingredientID = $ingredientID and stockTrans_batchID = $primary_key and stockTrans_type = 'Used'";
            $this->db->query($queryStr);
        }
        
        $this->create_batch_code($primary_key, $post_array['batch_cookDate']);
    }
    
    // callback after delete for the batch function (to remove all related entries from the stockTrans table)
    function _delete_batch_callback($primary_key) {
        $queryStr = "DELETE from stockTrans where stockTrans_batchID = $primary_key";
        $this->db->query($queryStr);
    }
    
     public function editingredients($row)
    {
            try{
                    $crud = new grocery_CRUD();
                    $crud->set_theme('datatables');
                    $crud->set_table('recipeItems');
                    $crud->where('recipeID',$row);
                    $crud->set_relation('ingredientID','ingredients', 'ingredientName');
                    $crud->set_relation('recipeID','recipes','recipeName');
                    $crud->columns('recipeID','ingredientID','quantity', 'recipeUnits');
                    $crud->fields('recipeID','ingredientID', 'quantity', 'recipeUnits');

                    $crud->edit_fields('recipeID','ingredientID', 'quantity', 'recipeUnits');
                    
                    $crud->display_as('recipeID', 'Recipe');
                    $crud->display_as('ingredientID', 'Ingredient');
                    $crud->display_as('recipeUnits', 'Unit of Measure');
                    
                    $crud->callback_edit_field('recipeID',array($this,'edit_field_callback_1'));
                    $output = $crud->render();
                    $this->_example_output($output);
            }catch(Exception $e) {
                    show_error($e->getMessage().' --- '.$e->getTraceAsString());
            }
     }

    public function editbatch($row)
    {
            try{
                    $crud = new grocery_CRUD();
                    $crud->set_theme('datatables');
                    
                    $crud->set_model('Edit_batch_model2');
                    
                    $crud->set_table('stockTrans');
                    $crud->where('stockTrans_batchID',$row);
                    $crud->where('stockTrans_type','Used');
                    $crud->set_relation('stockTrans_batchID', 'batch', 'batch_batchCode');
                    //$crud->set_relation('stockTrans_subBatchID', 'batch', 'batch_batchCode');
                    $crud->set_relation('stockTrans_ingredientID', 'ingredients', 'ingredientName');
                    
                    $crud->columns('stockTrans_ingredientID', 'Ingredient Batch', 'stockTrans_quantity');
                    $crud->display_as('stockTrans_ingredientID', 'Ingredient');
                    $crud->display_as('stockTrans_quantity', 'Quantity');

                    $crud->callback_column('Ingredient Batch', array($this, '_callback_ingredientbatch_column'));
                    
                    $state = $crud->getState();
                    // if editing the 
                    if ($state == 'edit') {
                        $stateinfo = $crud->getStateInfo();
                        $stockTransID = $stateinfo->primary_key;
                        $stockArray = $this->Edit_batch_model2->get_stock_array($row, $stockTransID);
                        $crud->field_type('stockTrans_subBatchID','dropdown',$stockArray);
                    }
                    $output = $crud->render();
                    $this->_example_output($output);
            }catch(Exception $e) {
                    show_error($e->getMessage().' --- '.$e->getTraceAsString());
            }
     }

    // populate Ingredient Batch column when editing the Ingredients in a Batch.
    public function _callback_ingredientbatch_column($stockTransID, $row)
    {
        // get the BatchCode (if one has been defined) for the ingredient
        $stockTransID = $row->stockTrans_id;
        $query = $this->db->query("SELECT batch_batchCode from stockTrans join batch on stockTrans_subBatchID = batch.batch_id where stockTrans_ID = $stockTransID");
        $batchRow = $query->row();
        
        // check if this ingredient is created by a recipe, or not
        $ingredientID = $row->stockTrans_ingredientID;
        $query = $this->db->query("SELECT recipes_ingredientID from recipes where recipes_ingredientID = $ingredientID");
        $ingredientRow = $query->row();
        
        // if the ingredient is created by a recipe, and there is a batch_id set, return the batchCode
        // else return some text to say a Batch is not specified
        // else return some text to say the ingredient is a raw ingredient
        if(isset($ingredientRow)) {
            if(isset($batchRow)) {
                return $batchRow->batch_batchCode;
            } else {
                return "Batch not set";
            }
        }else{
            return "Raw Ingredient";
        }
    }
     
    function edit_field_callback_1($value, $primary_key)
    {
        return '<input type="text" value="'.$value.'" name="recipeID" readonly>';
    }
         
	public function valueToEuro($value, $row)
	{
		return $value.' &euro;';
	}

    public function create_batch_code($batchid, $cookdate) {
        $date_array = explode('/', $cookdate);
        
        $y = substr($date_array[2], -2);
        $m = sprintf("%02d", $date_array[1]);
        $d = sprintf("%02d", $date_array[0]);

        $mysqldate = $date_array[2].$m.$d;
        
        $months = array("01" => "A",
                        "02" => "B",
                        "03" => "C",
                        "04" => "D",
                        "05" => "E",
                        "06" => "F",
                        "07" => "G",
                        "08" => "H",
                        "09" => "I",
                        "10" => "J",
                        "11" => "K",
                        "12" => "L");        

        //calculate the batch sequence for the day (don't need to + 1 as this batch has already been added)
        $query = $this->db->query("SELECT count(batch_cookDate) as numBatches from batch where batch_cookDate=$mysqldate group by batch_cookDate");
        $row = $query->row();
        $count = $row->numBatches;
        
        $batchCode = $y.$months[$m].$d.$count;
        
        $queryStr = "UPDATE batch SET batch_batchCode = '$batchCode' where batch_id = $batchid";
        $this->db->query($queryStr);
    }

    public function temperatures()
    {
            try{
                    $crud = new grocery_CRUD();
                    
                    $crud->set_theme('datatables');
                    $crud->set_table('tempRecords');
                    
                    $crud->set_relation('tempRecords_batchid', 'batch', 'batch_batchCode');
                    //$crud->columns('batch_batchCode', 'batch_recipeID','batch_cookDate','batch_quantity');
                    
                    $crud->display_as('tempRecords_batchID','Batch Code');
                    //$crud->display_as('batch_recipeID','Recipe');
                    //$crud->display_as('batch_cookDate','Date Made');
                    //$crud->display_as('batch_quantity','Batch Quantity');
                    

                    $output = $crud->render();
                    $this->_example_output($output);
                    
            }catch(Exception $e) {
                    show_error($e->getMessage().' --- '.$e->getTraceAsString());
            }
     }
    
}
