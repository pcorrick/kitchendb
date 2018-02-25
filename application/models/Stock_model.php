<?php
class Stock_model extends grocery_CRUD_Model
{
    function get_list()
    {
	 if($this->table_name === null)
	  return false;
	
	 $select = "{$this->table_name}.*";
	
   
  // ADD YOUR SELECT FROM JOIN HERE <------------------------------------------------------
  // for example $select .= ", user_log.created_date, user_log.update_date";
  $select .= ", ingredients.*, batch.*";
 
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
    $this->db->join('recipes',$this->table_name . '.ingredients_id = recipes.recipes_ingredientID', 'LEFT');
    $this->db->join('batch','batch.batch_recipeID = recipes.recipes_id', 'LEFT');
  
    $this->db->group_by('ingredients.ingredients_id');
    $this->db->group_by('batch.batch_id');
    
    $this->db->order_by('ingredientName', 'asc');

    $results = $this->db->get($this->table_name)->result();
	
	 return $results;
    }
    
    public function get_last_stocktake_data($ingredientid)
    {
        $query = $this->db->query("select stockTrans_date, stockTrans_quantity as qty, stockTrans_quantityUOM as qtyUOM from stockTrans where stockTrans_ingredientID='$ingredientid' and stockTrans_type='Stocktake' order by stockTrans_date desc");
        $arrayUOM = $this->getQtyArray($query);
        $result = $query->row();
        if(isset($result)) return array("Date"=>$result->stockTrans_date,"arrayUOM"=>$arrayUOM);
        else return array("Date"=>0,"arrayUOM"=>$arrayUOM);
    }    
    
    // $type = Created, Purchase, Used, Planned Created, Planned Used
    // if $batchid is null, it's a raw ingredient, otherwise it's a batch
    // TO DO: this just returns sum of Purchase, Created, etc. Need a Stock function to do Purchase - Used???
    //
    // return arrayUOM
    public function get_stocktrans_since($batchid, $ingredientid, $date, $type)
    {
        if(!is_null($batchid)) {
            $query = $this->db->query("select sum(stockTrans_quantity) as qty, stockTrans_quantityUOM as qtyUOM from stockTrans where stockTrans_ingredientID = $ingredientid and stockTrans_type = '$type' and stockTrans_date >= '$date' and stockTrans_subBatchID = $batchid group by stockTrans_ingredientID, stockTrans_quantityUOM, stockTrans_subBatchID");
        } else {
            $query = $this->db->query("select sum(stockTrans_quantity) as qty, stockTrans_quantityUOM as qtyUOM from stockTrans where stockTrans_ingredientID = $ingredientid and stockTrans_type = '$type' and stockTrans_date >= '$date' group by stockTrans_ingredientID, stockTrans_quantityUOM");
        }
        
        return $this->getQtyArray($query);
    }
    
    public function get_batch_created($batchid)
    {
        $query = $this->db->query("select stockTrans_quantity as qty, stockTrans_quantityUOM as qtyUOM from stockTrans where stockTrans_batchid = '$batchid' and stockTrans_type = 'Created'");
        return $this->getQtyArray($query);
    }
    
    //return a key->value array of quantities
    public function getQtyArray($query) {
        $queryrow = $query->row();
        $arrayUOM = array('g'=>0, 'kg'=>0, 'pieces'=>0);
        if(isset($queryrow)) {
            foreach($query->result() as $row) {
                $arrayUOM[$row->qtyUOM] = (float)$row->qty;
            }
        }
        return $arrayUOM;
    }

    // format a key->value array of quantities into a single rounded unit
    public function formatUOM($arrayUOM) {
        if($arrayUOM['g'] == 0 && $arrayUOM['kg'] == 0 && $arrayUOM['pieces'] == 0) {
            return '0';
        }
        
        if($arrayUOM['pieces'] > 0) {
            return $arrayUOM['pieces'].' pieces';
        }
        
        $arrayUOM['kg'] = $arrayUOM['kg'] + ($arrayUOM['g']/1000);
        $arrayUOM['g'] = $arrayUOM['kg'] * 1000;
        if($arrayUOM['kg'] < 1 && $arrayUOM['kg'] > -1){
            return round($arrayUOM['g'],3).' g';
        } else {
            return round($arrayUOM['kg'],3).' kg';
        }
    }
    
    // array1 + (sign*array2)
    // return arrayUOM of the result
    public function addArrayUOM($array1, $array2, $sign) {
        foreach ($array1 as $uom => $val) {
            if(array_key_exists($uom, $array1) && array_key_exists($uom, $array2))
                $result[$uom] = $array1[$uom] + $sign*$array2[$uom];
        }
        return $result;
    }
}