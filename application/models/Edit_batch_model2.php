<?php
class Edit_batch_model2 extends grocery_CRUD_Model
{
    function get_list()
    {
	 if($this->table_name === null)
	  return false;
	
	 $select = "{$this->table_name}.*";
	
   
  // ADD YOUR SELECT FROM JOIN HERE <------------------------------------------------------
  // for example $select .= ", user_log.created_date, user_log.update_date";
  $select .= ", batch.*, ingredients.*";
 
	 if(!empty($this->relation))
	  foreach($this->relation as $relation)
	  {
	   list($field_name , $related_table , $related_field_title) = $relation;
	   $unique_join_name = $this->_unique_join_name($field_name);
	   $unique_field_name = $this->_unique_field_name($field_name);
	  
    if(strstr($related_field_title,'{'))
	    $select .= ", CONCAT('".str_replace(array('{','}'),array("',COALESCE({$unique_join_name}.",", ''),'"),str_replace("'","\\'",$related_field_title))."') as $unique_field_name";
	   else	  
	    $select .= ", $unique_join_name.$related_field_title as $unique_field_name";
	  
	   if($this->field_exists($related_field_title))
	    $select .= ", {$this->table_name}.$related_field_title as '{$this->table_name}.$related_field_title'";
	  }
	 
	 $this->db->select($select, false);
	
    // ADD YOUR JOIN HERE for example: <------------------------------------------------------
    $this->db->join('ingredients', $this->table_name . '.stockTrans_ingredientID = ingredients.ingredients_id', 'LEFT');
    $this->db->join('batch', $this->table_name . '.stockTrans_batchID = batch.batch_ID', 'LEFT');
    $this->db->join('recipes', 'ingredients.ingredients_id = recipes.recipes_ingredientID', 'LEFT');
  
    $this->db->where('recipes.recipes_id IS NOT NULL');
  
    $results = $this->db->get($this->table_name)->result();
        
	return $results;
    }
    
    // get 
    public function get_current_stock($ingredientid, $batchid)
    {
        
        $query = $this->db->query("select stockTrans_quantity as stock from stockTrans where stockTrans_ingredientID = $ingredientid and stockTrans_type = 'Created' and stockTrans_batchID = $batchid");
        $row = $query->row();
        $created = $row->stock;
        
        $query = $this->db->query("select sum(stockTrans_quantity) as stock from stockTrans where stockTrans_ingredientID = $ingredientid and stockTrans_type = 'Used' and stockTrans_subBatchID = $batchid group by stockTrans_ingredientID, stockTrans_subBatchID, stockTrans_type");
        $row = $query->row();
        if(isset($row)) {
            $used = $row->stock;
        } else {
            $used = 0;
        }
        
        return $created - $used;
    }
    
    // get all stock of ingredient referenced in the stockTrans row
    // return array of {batchID, stock quantity}
    public function get_stock_array($stockTransID)
	{
        $table = 'stockTrans';

        // find ingredientID that we are editing
        $query = $this->db->query("select stockTrans_ingredientID from stockTrans where stockTrans_ID = $stockTransID");
        $ingredientid = $query->row()->stockTrans_ingredientID;
        
        // find all 'created' rows in stockTrans table for the relevant ingredientID
        $this->db->select('stockTrans_id, stockTrans_ingredientID, stockTrans_type, stockTrans_batchID, batch_batchCode, batch_ID, ingredients.ingredientName');
        $this->db->join('batch',$table.'.stockTrans_batchID = batch.batch_ID', 'LEFT');
        $this->db->join('ingredients',$table.'.stockTrans_ingredientID = ingredients.ingredients_id', 'LEFT');
        $this->db->where('stockTrans_ingredientID',$ingredientid);
        $this->db->where('stockTrans_type','Created');
        $result = $this->db->get($table);
        $myarray = array();
        foreach ($result->result() as $stockrow)
        {
            if(is_null($stockrow->stockTrans_batchID)) {
                $myarray = array("0" =>"Error - missing batchid in ingredient");
            } else {
                $currentStock = $this->get_current_stock($ingredientid, $stockrow->stockTrans_batchID);
                $myarray[$stockrow->batch_ID] = "Stock (".$currentStock.") Batch Code: ".$stockrow->batch_batchCode." - ".$stockrow->ingredientName;
            }
        }
        
        if(empty($myarray)) {
            $myarray = array("0" =>"Error - no stock available");
        }
        
        return $myarray;
	}
    
}