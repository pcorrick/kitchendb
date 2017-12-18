<?php
class Edit_batch_model extends grocery_CRUD_Model
{
    function get_list()
    {
	 if($this->table_name === null)
	  return false;
	
	 $select = "{$this->table_name}.*";
	
   
  // ADD YOUR SELECT FROM JOIN HERE <------------------------------------------------------
  // for example $select .= ", user_log.created_date, user_log.update_date";
  $select .= ", batch.*, stockCreated.*, ingredients.*";
 
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
    $this->db->join('stockCreated',$this->table_name . '.stockUsed_stockCreatedID = stockCreated.stockCreated_id', 'LEFT');
    $this->db->join('ingredients', $this->table_name . '.stockUsed_ingredientID = ingredients.id', 'LEFT');
    $this->db->join('batch', 'stockCreated.stockCreated_batchID = batch.batch_ID', 'LEFT');
  
    $results = $this->db->get($this->table_name)->result();
        
	return $results;
    }
    
    public function get_stock_array($batchid, $stockUsedID)
	{
        $table = 'stockCreated';

        $query = $this->db->query("select stockUsed_ingredientID from stockUsed where stockUsed_ID = $stockUsedID");
        $ingredientid = $query->row()->stockUsed_ingredientID;
        
        $this->db->select('stockCreated_id, stockCreated_ingredientID, batch_batchCode, ingredients.ingredientName');
        $this->db->join('batch',$table.'.stockCreated_batchID = batch.batch_ID', 'LEFT');
        $this->db->join('ingredients',$table.'.stockCreated_ingredientID = ingredients.id', 'LEFT');
        $this->db->where('ingredients.id',$ingredientid);
        $result = $this->db->get($table);		
        $myarray = array();
        foreach ($result->result() as $stockrow)
        {
            $myarray[$stockrow->stockCreated_id] = "Batch Code: ".$stockrow->batch_batchCode." - ".$stockrow->ingredientName;
        }
        
        if(empty($myarray)) {
            $myarray = array("0" =>"Error - no stock available");
        }
        
        return $myarray;
	}
}