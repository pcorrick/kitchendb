<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class Kitchendb extends CI_Controller {

	public function __construct()
	{
		parent::__construct();

        
        $this->load->add_package_path(APPPATH.'third_party/ion_auth/');
        $this->load->library('ion_auth');
        
        if (!$this->ion_auth->logged_in()) {
            //redirect them to the login page
            redirect('admin/user/login', 'refresh');
        }
        
		$this->load->database();
		$this->load->helper('url');

		$this->load->library('grocery_CRUD');
	}

	public function _example_output($output = null)
	{
        $this->load->view('templates/header');
		$this->load->view('kitchendb.php',(array)$output);
        $this->load->view('templates/footer');
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
                        $this->db->order_by('ingredientName', 'asc');
                        $output = $crud->render();
                        $this->_example_output($output);
                }catch(Exception $e) {
                        show_error($e->getMessage().' --- '.$e->getTraceAsString());
                }
         }

     // CRUD for managing ingredients (either adding purchased raw ingredients, or doing a stocktake
     // purchase = add ingredient to existing stock
     // stocktake = re-baseline the currently held ingredient quantity
    public function manage()
    {
        try{
                $crud = new grocery_CRUD();
                $crud->set_theme('flexigrid');
                $crud->set_table('stockTrans');
                $crud->set_subject('Stocktake');
                
                $where = "stockTrans_type = 'Stocktake' OR stockTrans_type = 'Purchase'";
                $crud->where($where);
                
                $crud->columns('IID','stockTrans_ingredientID','stockTrans_type', 'stockTrans_quantity', 'stockTrans_quantityUOM','stockTrans_date');
                $crud->display_as('stockTrans_ingredientID','Ingredient');
                
                $crud->callback_column('IID', array($this, '_callback_IID_column'));
                $crud->callback_column('stockTrans_ingredientID', array($this, '_callback_ingredient_column'));
                
                //$crud->set_relation('stockTrans_ingredientID', 'ingredients', 'ingredientName');
                
                $state = $crud->getState();
                // customise the ingredients dropdown to exclude ingredients that should
                // be created by adding a new batch
                if ($state == 'add' || $state == 'edit') {
                    $stateinfo = $crud->getStateInfo();
                    $ingredientsArray = $this->get_raw_ingredients_only();
                    $crud->field_type('stockTrans_ingredientID','dropdown',$ingredientsArray);
                }
                
                $output = $crud->render();
                $this->_example_output($output);
        }catch(Exception $e) {
                show_error($e->getMessage().' --- '.$e->getTraceAsString());
        }
     }
     
    public function _callback_IID_column($stockTransID, $row)
    {
        return $row->stockTrans_ingredientID;
    }
    
    public function _callback_ingredient_column($stockTransID, $row)
    {
        $ingredientID = $row->IID;
        $query = $this->db->query("select ingredientName from ingredients where ingredients.ingredients_id='$ingredientID'");
        $result = $query->row();
        return $result->ingredientName;
    }
    
    public function get_raw_ingredients_only()
	{
        $query = $this->db->query("SELECT ingredients.ingredients_id, ingredientName FROM ingredients left outer join recipes on recipes.recipes_ingredientID = ingredients.ingredients_id where recipes.recipes_id is null");
        $myarray = array();
        foreach ($query->result() as $row) {
            $myarray[$row->ingredients_id] = $row->ingredientName;
        }
        
        if(empty($myarray)) {
            $myarray = array("0" =>"Error - no raw ingredients in database");
        }

        return $myarray;
	}
     
    public function stock()
    {
        try{
                $crud = new grocery_CRUD();
                $crud->set_theme('flexigrid');
                $crud->set_table('ingredients');
                $crud->set_subject('Stock');
                $crud->set_model('Stock_model');

                $crud->columns('ingredient_type', 'ingredientName', 'batch_batchCode', 'Last_Stocktake_Qty','Purchased_Since','Created_Since','Used_Since', 'Current_Stock');
                
                $crud->callback_column('ingredient_type', array($this, '_callback_ingredienttype_column'));
                $crud->callback_column('Last_Stocktake_Qty', array($this, '_callback_stocktakeqty_column'));
                $crud->callback_column('Purchased_Since', array($this, '_callback_purchasedsince_column'));
                $crud->callback_column('Created_Since', array($this, '_callback_createdsince_column'));

                $crud->callback_column('Used_Since', array($this, '_callback_usedsince_column'));
                $crud->callback_column('Current_Stock', array($this, '_callback_currentstock_column'));
                //$crud->callback_column('Planned Created', array($this, '_callback_plannedcreated_column'));
                //$crud->callback_column('Planned Used', array($this, '_callback_plannedused_column'));
                //$crud->callback_column('Stock After Plan', array($this, '_callback_stockafterplan_column'));

                $crud->unset_add();
                $crud->unset_edit();
                $crud->unset_delete();
                $crud->unset_read();
               
                $output = $crud->render();
                $this->_example_output($output);
        }catch(Exception $e) {
                show_error($e->getMessage().' --- '.$e->getTraceAsString());
        }
     }
    
    public function _callback_ingredienttype_column($ingredientID, $row)
    {
        $query = $this->db->query("SELECT recipes_id FROM ingredients left outer join recipes on recipes.recipes_ingredientID = ingredients.ingredients_id where ingredients.ingredients_id = '$row->ingredients_id'");
        $result = $query->row();   
        if($result->recipes_id > 0) return 'Batch';
        else return 'Raw';
    }

    public function _callback_stocktakeqty_column($ingredientID, $row)
    {
        $data = $this->Stock_model->get_last_stocktake_data($row->ingredients_id);
        
        if($data == 0) return '-';
        else return $this->Stock_model->formatUOM($data['arrayUOM']).' ('.$data['Date'].')';
    }
    
    // ingredients created by cooking a batch are "Created"
    // raw ingredients are noted as "Purchase"
    public function _callback_purchasedsince_column($ingredientID, $row)
    {
        $date = $this->Stock_model->get_last_stocktake_data($row->ingredients_id)['Date'];        
        $purchaseArrayUOM = $this->Stock_model->get_stocktrans_since(null, $row->ingredients_id, $date, "Purchase");
        
        return $this->Stock_model->formatUOM($purchaseArrayUOM);
    }
    
    // ingredients created by cooking a batch are "Created"
    // raw ingredients are noted as "Purchase"
    public function _callback_createdsince_column($ingredientID, $row)
    {
        $createdArrayUOM = $this->Stock_model->get_batch_created($row->batch_id);
        return $this->Stock_model->formatUOM($createdArrayUOM);
    }
    
    public function _callback_usedsince_column($ingredientID, $row)
    {
        $date = $this->Stock_model->get_last_stocktake_data($row->ingredients_id)['Date'];
        $usedArrayUOM = $this->Stock_model->get_stocktrans_since($row->batch_id, $row->ingredients_id, $date, "Used");
        
        return $this->Stock_model->formatUOM($usedArrayUOM);
    }
    
    // current stock based on last stocktake quantity (or batch Created quantity)
    // Calculate as:
    // For raw ingredients:   last stocktake quantity + purchased since - used
    // For batch ingredients: created - used
    public function _callback_currentstock_column($ingredientID, $row)
    {
        $stocktakedata = $this->Stock_model->get_last_stocktake_data($row->ingredients_id);
        $purchasedata = $this->Stock_model->get_stocktrans_since(null, $row->ingredients_id, $stocktakedata['Date'], "Purchase");
        $createdata = $this->Stock_model->get_batch_created($row->batch_id);
        $useddata = $this->Stock_model->get_stocktrans_since($row->batch_id, $row->ingredients_id, $stocktakedata['Date'], "Used");
        
        if($row->ingredient_type == "Raw") {
            $result = $this->Stock_model->addArrayUOM($stocktakedata['arrayUOM'], $purchasedata, 1);
            $result = $this->Stock_model->addArrayUOM($result, $useddata, -1);
        } else {
            $result = $this->Stock_model->addArrayUOM($createdata, $useddata, -1);
        }
        //if($result['pieces'] > 0) var_dump($result);
        return $this->Stock_model->formatUOM($result);
    }
    
    /*
    public function _callback_plannedcreated_column($ingredientID, $row)
    {
        $arrayUOM = $this->getStock($row->stockTrans_batchID, $row->IID, "Planned_Created");
        return $this->formatUOM($arrayUOM);
    }
    
    public function _callback_plannedused_column($ingredientID, $row)
    {
        $arrayUOM = $this->getStock($row->stockTrans_batchID, $row->IID, "Planned_Used");
        return $this->formatUOM($arrayUOM);
    }

    public function _callback_stockafterplan_column($ingredientID, $row)
    {
        $batchid = $row->stockTrans_batchID;
        //$ingredientID = $row->ingredients_id;
        $ingredientID = $row->IID;

        $created = $this->getStock($batchid, $ingredientID, "Created");
        $used = $this->getStock($batchid, $ingredientID, "Used");
        $planned_created = $this->getStock($batchid, $ingredientID, "Planned_Created");
        $planned_used = $this->getStock($batchid, $ingredientID, "Planned_Used");

        foreach ($created as $uom => $val) {
            $stockafterplan[$uom] = $created[$uom] + $planned_created[$uom] - $used[$uom] - $planned_used[$uom];
        }
        
        return $this->formatUOM($stockafterplan);
    }
    */

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
                    
                    $crud->columns('batch_batchCode', 'batch_recipeID','batch_cookDate','batch_quantity','batch_planned');
                    $crud->add_fields('batch_batchCode', 'batch_recipeID','batch_cookDate','batch_quantity','batch_planned');
                    $crud->edit_fields('batch_recipeID', 'batch_batchCode', 'batch_cookDate','batch_quantity','batch_planned');
                    
                    $crud->display_as('batch_batchCode','Batch Code');
                    $crud->display_as('batch_batchCode','Batch Code');
                    $crud->display_as('batch_recipeID','Recipe');
                    $crud->display_as('batch_cookDate','Date Made');
                    $crud->display_as('batch_quantity','Batch Quantity');
                    
                    $crud->field_type('batch_planned', 'true_false', array('Created', 'Planned'));
                    $crud->field_type('batch_batchCode', 'hidden');

                    $crud->required_fields('batch_recipeID', 'batch_quantity', 'batch_cookDate','batch_planned');
                    
                    $crud->callback_column('batch_recipeID', array($this, '_callback_recipe_column'));
                    
                    $crud->add_action('Edit Batch','','kitchendb/editbatch','ui-icon-plus');

                    $state = $crud->getState();
                    // if adding or editing a batch, customise the recipe dropdown to include the quantity created
                    // need to remove relation on this field to make sure custom dropdown works
                    if ($state == 'add' || $state == 'edit') {
                        $stateinfo = $crud->getStateInfo();
                        $recipesArray = $this->get_recipe_details();
                        $crud->field_type('batch_recipeID','dropdown',$recipesArray);
                    }
                    
                    $crud->callback_before_insert(array($this, '_insert_batch_before_callback'));
                    $crud->callback_after_insert(array($this, '_add_stock_transaction_callback'));
                    
                    $crud->callback_after_delete(array($this, '_delete_batch_callback'));
                    
                    $crud->callback_before_update(array($this, '_update_batch_before_callback'));
                    $crud->callback_after_update(array($this, '_update_batch_after_callback'));
                    
                    $output = $crud->render();
                    $this->_example_output($output);
                    
            }catch(Exception $e) {
                    show_error($e->getMessage().' --- '.$e->getTraceAsString());
            }
     }

    public function _callback_recipe_column($batchID, $row)
    {
        $batch_recipeID = $row->batch_recipeID;
        $query = $this->db->query("select recipeName from recipes where recipes_id='$batch_recipeID'");
        $result = $query->row();
        return $result->recipeName;
    }
     
    public function get_recipe_details()
	{
        $query = $this->db->query("select recipes_id, recipeName, recipes_outputQuantity, recipes_outputUOM from recipes");
        $myarray = array();
        foreach ($query->result() as $row) {
            $myarray[$row->recipes_id] = $row->recipeName." (".$row->recipes_outputQuantity . " " . $row->recipes_outputUOM ." per batch)";
        }
        
        if(empty($myarray)) {
            $myarray = array("0" =>"Error - no recipes in database");
        }

        return $myarray;
	}

     // callback to create batch code
     function _insert_batch_before_callback($post_array) {

            $batchPlanned = $post_array['batch_planned'];
            $post_array['batch_batchCode'] = 'Test';
            if($batchPlanned == True) {
                // only create a batch_code if it's at actual batch, otherwise mark it as Planned
                $post_array['batch_batchCode'] = "Planned";
                $post_array['batch_cookDate'] = NULL;
            } else {
                // create a proper batch code
                $post_array['batch_batchCode'] = $this->create_batch_code($post_array['batch_cookDate']);
            }
            return $post_array;
     }
    
     // callback after insert for the batch function, to insert data into stockTrans table
     function _add_stock_transaction_callback($post_array, $primary_key) {

            $recipeID = $post_array['batch_recipeID'];
            $batchQty = $post_array['batch_quantity'];
            $batchPlanned = $post_array['batch_planned'];
            $batchDate = $post_array['batch_cookDate'];
            
            if($batchPlanned == True) {
                $created = "Planned_Created";
                $used = "Planned_Used";
            } else {
                $created = "Created";
                $used = "Used";
            }
            
            // find the ingredientID column of the relevant recipe
            $query = $this->db->query("SELECT recipes_ingredientID, recipes_outputQuantity, recipes_outputUOM from recipes where recipes.recipes_id=$recipeID");
            $row = $query->row();
            $ingredientID = $row->recipes_ingredientID;
            $outputQty = $row->recipes_outputQuantity;
            $outputUOM = $row->recipes_outputUOM;
            
            //if the ingredientID is not null (i.e. this batch is an ingredient, so need to add it to stock)
            if(!(is_null($ingredientID))) {
                $qtyCreated = $outputQty * $batchQty;
                $queryStr = "INSERT into stockTrans (stockTrans_ingredientID, stockTrans_batchID, stockTrans_type, stockTrans_quantity, stockTrans_quantityUOM, stockTrans_date) VALUES ($ingredientID, $primary_key, '$created', $qtyCreated, '$outputUOM', '$batchDate')";
                $this->db->query($queryStr);
            }
            
            // add transactions for the ingredients that make up the batch
            $query = $this->db->query("SELECT ingredientID, quantity, recipeUnits from recipeItems where recipeID=$recipeID");
            
            foreach($query->result() as $row) {
                $ingredientID = $row->ingredientID;
                $qty = $batchQty * $row->quantity;
                $uom = $row->recipeUnits;
                $queryStr = "INSERT into stockTrans (stockTrans_ingredientID, stockTrans_batchID, stockTrans_type, stockTrans_quantity, stockTrans_quantityUOM, stockTrans_date) VALUES ($ingredientID, $primary_key, '$used', $qty, '$uom', '$batchDate')";
                $this->db->query($queryStr);
            }
            return $post_array;
    }

    // callback before update batch
    function _update_batch_before_callback($post_array, $primary_key) {

        $planned = $post_array['batch_planned'];
        $currentBatchcode = $post_array['batch_batchCode'];

        // if current batchCode is 'Planned', and new status is planned = false
        if($currentBatchcode == "Planned" && $planned == False) {
            $post_array['batch_batchCode'] = $this->create_batch_code($post_array['batch_cookDate']);
        }

        return $post_array;
     }
    
     // callback after update for the batch function
     // e.g. if quantity or planned status of the batch changes, need to update the stockTrans table quantities
    function _update_batch_after_callback($post_array, $primary_key) {
        $recipeID = $post_array['batch_recipeID'];
        $batchQty = $post_array['batch_quantity'];
        $planned = $post_array['batch_planned'];
        $batchDate = $post_array['batch_cookDate'];

        if($planned == True) {
            $created = "Planned_Created";
            $used = "Planned_Used";
        } else {
            $created = "Created";
            $used = "Used";
        }
        
        // find the ingredientID column of the relevant recipe
        $query = $this->db->query("SELECT recipes_ingredientID, recipes_outputQuantity, recipes_outputUOM from recipes where recipes.recipes_id=$recipeID");
        $row = $query->row();
        $ingredientID = $row->recipes_ingredientID;
        $outputQty = $row->recipes_outputQuantity;
        $outputUOM = $row->recipes_outputUOM;
            
        //if the ingredientID is not null (i.e. this batch is an ingredient, so need to add it to stock)
        if(!(is_null($ingredientID))) {
            $qtyCreated = $outputQty * $batchQty;
            $queryStr = "UPDATE stockTrans SET stockTrans_quantity = $qtyCreated, stockTrans_quantityUOM = '$outputUOM', stockTrans_type = '$created', stockTrans_date = '$batchDate' WHERE stockTrans_batchID = $primary_key and stockTrans_type like '%Created%' and stockTrans_ingredientID = $ingredientID";
            $this->db->query($queryStr);
        }
            
        // add transactions for the ingredients that make up the batch
        $query = $this->db->query("SELECT ingredientID, quantity, recipeUnits from recipeItems where recipeID=$recipeID");
            
        foreach($query->result() as $row) {
            $ingredientID = $row->ingredientID;
            $qty = $batchQty * $row->quantity;
            $uom = $row->recipeUnits;
            $queryStr = "UPDATE stockTrans SET stockTrans_quantity = $qty, stockTrans_quantityUOM = '$uom', stockTrans_type = '$used', stockTrans_date = '$batchDate' WHERE stockTrans_ingredientID = $ingredientID and stockTrans_batchID = $primary_key and stockTrans_type like '%Used%'";
            $this->db->query($queryStr);
        }
        
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
                    //$crud->set_theme('datatables');
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
                    //$crud->set_theme('datatables');
                    
                    $crud->set_model('Edit_batch_model2');
                    
                    $crud->set_table('stockTrans');
                    $crud->where('stockTrans_batchID',$row);
                    $crud->like('stockTrans_type','Used');
                    $crud->set_relation('stockTrans_ingredientID', 'ingredients', 'ingredientName');
                    
                    $crud->columns('stockTrans_ingredientID', 'Ingredient Batch', 'stockTrans_quantity');
                    $crud->display_as('stockTrans_ingredientID', 'Ingredient');
                    $crud->display_as('stockTrans_quantity', 'Quantity');

                    $crud->callback_column('Ingredient Batch', array($this, '_callback_ingredientbatch_column'));
                    
                    $state = $crud->getState();
                    // if editing, customise the stockTrans_subBatchID dropdown
                    if ($state == 'edit') {
                        $stateinfo = $crud->getStateInfo();
                        $stockTransID = $stateinfo->primary_key;
                        $stockArray = $this->Edit_batch_model2->get_stock_array($stockTransID);
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

    // grocery_crud config must be sql-date
    public function create_batch_code($cookdate) {
        
        $date_array = explode('-', $cookdate);
        
        $y = substr($date_array[0], -2);
        $m = sprintf("%02d", $date_array[1]);
        $d = sprintf("%02d", $date_array[2]);

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

        //calculate the batch sequence for the day
        $query = $this->db->query("SELECT count(batch_cookDate) as numBatches from batch where batch_cookDate=$cookdate and batch_batchCode != 'Planned' group by batch_cookDate");
        $row = $query->row();
        $count = $row->numBatches + 1;
        
        $batchCode = $y.$months[$m].$d.$count;
        return $batchCode;
    }

    public function temperatures()
    {
            try{
                    $crud = new grocery_CRUD();
                    
                    //$crud->set_theme('datatables');
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
