<?php
/**
 * Simple Category Tree
 * @Version: Beta 2
 * @Authour: yellow1912
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */ 

define ('SCT_REBUILD_TREE','false');
class simple_categories_tree{
	var $category_tree = array();
	var $is_deepest_cats_built = false;
	var $is_product_tree_built = false;
	var $parent_open_tag = '';
	var $parent_close_tag = '';
	var $child_open_tag = '';
	var $child_close_tag = '';
	var $current_id = 0;
	var $exceptional_list = array();
	var $product_tree = array();
	function init(){
		if(SCT_REBUILD_TREE != 'false' || count($this->category_tree) == 0){
			global $languages_id, $db;
			$categories_query = "select c.categories_id, cd.categories_name, c.parent_id
	                      from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd
	                      where c.categories_id = cd.categories_id
	                      and c.categories_status=1
						  and cd.language_id = '" . (int)$_SESSION['languages_id'] . "'
	                      order by c.parent_id, c.sort_order, cd.categories_name";
			$categories = $db->Execute($categories_query);

			// reset the tree first
			$this->category_tree = array(); 
			$this->is_deepest_cats_built = false;
			$this->is_product_tree_built = false;
			while (!$categories->EOF) {
				$this->category_tree[$categories->fields['categories_id']]['name'] = $categories->fields['categories_name'];
				$this->category_tree[$categories->fields['categories_id']]['parent_id'] = $categories->fields['parent_id'];
				$this->category_tree[$categories->fields['categories_id']]['path'][] = $categories->fields['categories_id'];
				$this->category_tree[$categories->fields['parent_id']]['sub_cats'][] = $categories->fields['categories_id'];
				$categories->MoveNext();
			}
			
			// walk through the array and build sub/cPath and other addtional info needed
			foreach($this->category_tree as $key => $value){
				// add sub 'class' for print-out purpose
				$this->category_tree[$key]['sub'] = isset($this->category_tree[$key]['sub_cats']) ? 'has_sub' : 'no_sub';
				// only merge if parent cat is not 0
				if(isset($this->category_tree[$key]['parent_id']) && $this->category_tree[$key]['parent_id'] > 0){
					if(is_array($this->category_tree[$this->category_tree[$key]['parent_id']]['path']) && count($this->category_tree[$this->category_tree[$key]['parent_id']]['path'])> 0)
						$this->category_tree[$key]['path'] = array_merge($this->category_tree[$this->category_tree[$key]['parent_id']]['path'],$this->category_tree[$key]['path']);
				}
				$this->category_tree[$key]['cPath'] = isset($this->category_tree[$key]['path']) ? implode('_',$this->category_tree[$key]['path']) : $key;
	
			}
			// for debugging using super global mod
			// $_POST['category_tree'] = $this->category_tree;
		}
		// This special portion of code was added to catch the current category selected
		$this->current_id = 0;
		$this->exceptional_list = array();
		if(isset($_GET['cPath'])){
			$this->current_id = $this->_get_categories_id($_GET['cPath']);
			$cPath = $this->retrieve_cpath($this->current_id);
			if(!empty($cPath)){
				$this->exceptional_list = explode('_', $cPath);
			}
		}
		
		
	}
	
	function retrieve_cpath($categories_id){
		$categories_id = $this->_get_categories_id($categories_id);
		return (isset($this->category_tree[$categories_id]['cPath']) ? $this->category_tree[$categories_id]['cPath'] : '');
	}
	
	function retrieve_categories_tree_array(){
		return $this->category_tree;
	}
	
	function retrieve_deepest_cats_array($categories_id){
		$categories_id = $this->_get_categories_id($categories_id);
		return (isset($this->category_tree[$categories_id]['deepest_cats']) ? $this->category_tree[$categories_id]['deepest_cats'] : array());
	}
	// 9 is a ridiculous level already. If you go deeper than that, you have some problem with performance + structure
	// Max level should be around 3
	// when strict is set to true, the tree will NOT expand even if it can, it will stick to the set max level
	function build_category_string($parent_tag = 'div', $child_tag = 'span', $divider = '', $categories_id = 0, 
									$max_level = 9, $include_root = false, $strict = false, $categories_with_no_links = array()){
		$categories_id = $this->_get_categories_id($categories_id);		
		$result = '';
		// don't check if max_level = 0, since we assume store owners are not crazy enough to do that
		// --> less check = faster
		if(isset($this->category_tree[$categories_id])){
			//
			$level = 0;
			$class = $this->_build_class($categories_id, $level);
			$this->_build_tags($parent_tag, $child_tag);
			// check if we should include the root or only its branches
			if($include_root && $categories_id > 0){
				$result = sprintf($this->parent_open_tag, $class).sprintf($this->child_open_tag, $class);
				$result .= $this->_build_category_string($categories_id, $level, $max_level, $divider, $strict, $class, $categories_with_no_links);
				$result .= $this->child_close_tag.$this->parent_close_tag;
			}
			else{
				$result .= $this->__build_category_string($categories_id, $level, $max_level, $divider, $strict, $categories_with_no_links);
			}
			
		}
		return $result;
	}

	function build_deepest_level_children(){
		if(!$this->is_deepest_cats_built){
			$this->_build_deepest_level_children(0);
			$this->is_deepest_cats_built = true;
		}
		// for debugging using super global mod
		// $_POST['category_tree'] = $this->category_tree;
	}

	// The functions below are internal and should not be called.
	function _build_category_string($categories_id, $level, $max_level, $divider, $strict, $class, $categories_with_no_links){
		if(!in_array($level, $categories_with_no_links))
		$result = $this->_build_category_link($this->category_tree[$categories_id]['name'],$this->category_tree[$categories_id]['cPath'], $class);
		else
		$result = $this->category_tree[$categories_id]['name'];
		$level++;
		$result .= $this->__build_category_string($categories_id, $level, $max_level, $divider, $strict, $categories_with_no_links);
		return $result;
	}

	function __build_category_string($categories_id, $level, $max_level, $divider, $strict, $categories_with_no_links){
		$result = '';
		if(($level < $max_level) || (!$strict && in_array($categories_id, $this->exceptional_list))){
			$class = $this->_build_class($categories_id, $level);
			// add product links
			if(isset($this->category_tree[$categories_id]['products'])){
				$count = count($this->category_tree[$categories_id]['products']);
				$result .= sprintf($this->parent_open_tag, $class);
				for($i=0; $i < $count; $i++){
					$result .= sprintf($this->child_open_tag, $class).$this->_build_product_link($this->category_tree[$categories_id]['products'][$i]['products_id'], $this->category_tree[$categories_id]['products'][$i]['products_name'], $this->category_tree[$categories_id]['cPath'], $class).$this->child_close_tag;
					$result .= ($i < ($count-1)) ? $divider : '';
				}
				$result .= $this->parent_close_tag;
			}
			if(isset($this->category_tree[$categories_id]['sub_cats'])){
					$result .= sprintf($this->parent_open_tag, $class);
					$count = count($this->category_tree[$categories_id]['sub_cats']);
					for($i=0; $i < $count; $i++){
						$class = $this->_build_class($this->category_tree[$categories_id]['sub_cats'][$i], $level);
						$result .= sprintf($this->child_open_tag, $class).$this->_build_category_string($this->category_tree[$categories_id]['sub_cats'][$i],$level,$max_level, $divider,$strict, $class, $categories_with_no_links).$this->child_close_tag;
						$result .= ($i < ($count-1)) ? $divider : '';
					}
					$result .= $this->parent_close_tag;
				}
		}
		return $result;
	}
	
	function _build_tags($parent_tag, $child_tag){
		if(!empty($parent_tag)){
			$this->parent_open_tag = "<$parent_tag class='%s'>";
			$this->parent_close_tag = "</$parent_tag>";		
		}
		else{
			$this->parent_open_tag = $this->parent_close_tag = '';		
		}
		if(!empty($child_tag)){
			$this->child_open_tag = "<$child_tag class='%s'>";
			$this->child_close_tag = "</$child_tag>";		
		}
		else{
			$this->child_open_tag = $this->child_close_tag = '';
		}
	}
	
	function count_sub_categories($categories_id){
		$categories_id = $this->_get_categories_id($categories_id);
		return isset($this->category_tree[$categories_id]['sub_cats']) ? 
				count($this->category_tree[$categories_id]['sub_cats']) : 0;
	}
	
	function _build_class($categories_id, $level){
		$class = "level_$level ".$this->category_tree[$categories_id]['sub'];
		if($categories_id == $this->current_id)
			$class .= ' current';
		return $class;
	}
	
	function _build_category_link($categories_name, $cPath, $class=''){
		if(!empty($class))
			$class = " class='$class'";
		return '<a'.$class.' href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $cPath) . '">'.$categories_name.'</a>';
		//return '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $cPath) . '" class="12345">'.$categories_name.'</a>';
	}
	
	function _build_product_link($products_id, $products_name, $cPath, $class=''){
		if(!empty($class))
			$class = " class='$class'";
		return '<a'.$class.' href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'cPath='.$cPath.'&amp;products_id='.$products_id) . '">'.$products_name.'</a>';
	}
	
	function _build_deepest_level_children($categories_id){
		$parent_id = isset($this->category_tree[$categories_id]['parent_id']) ? $this->category_tree[$categories_id]['parent_id'] : -1;
		if(isset($this->category_tree[$categories_id]['sub_cats'])){
			foreach($this->category_tree[$categories_id]['sub_cats'] as $sub_cat){
					// we now need to loop thru these cats, and find if they have sub_cats
					$this->_build_deepest_level_children($sub_cat);
			}
		}
		elseif($parent_id > 0){
			$this->category_tree[$parent_id]['deepest_cats'][] = $categories_id;
		}
		
		if($parent_id >= 0 && isset($this->category_tree[$categories_id]['deepest_cats'])){
			if(isset($this->category_tree[$parent_id]['deepest_cats']))
				$this->category_tree[$parent_id]['deepest_cats'] = array_merge($this->category_tree[$parent_id]['deepest_cats'],$this->category_tree[$categories_id]['deepest_cats']);
			else
				$this->category_tree[$parent_id]['deepest_cats'] = $this->category_tree[$categories_id]['deepest_cats'];
		}
	}
	
	function _get_categories_id($categories_id){
		if(!is_int($categories_id)){
			$temp = explode('_',$categories_id);
			$categories_id = end($temp);
		}
		return $categories_id;
	}
	
	// this will build a product tree
	// this function should NEVER be used on site with a few hundred products.
	// there is another drawback: this function only build the products for master cat
	function build_products_tree($categories_id){
		if(!$this->is_product_tree_built){
			global $db;
			$categories_id = implode(',',$categories_id);
			//$sql = 'SELECT p.master_categories_id, p.products_id, pd.products_name FROM '.
			//		TABLE_PRODUCTS.' p, '.TABLE_PRODUCTS_DESCRIPTION.' pd, '.TABLE_PRODUCTS_TO_CATEGORIES.'p2d WHERE ';
			$sql = 'SELECT p.master_categories_id, p.products_id, pd.products_name FROM '.
					TABLE_PRODUCTS.' p, '.TABLE_PRODUCTS_DESCRIPTION.' pd WHERE ';
			if(!empty($categories_id))
				$sql .= 'p.master_categories_id IN('.$categories_id.') AND ';
			$sql .= 'pd.products_id = p.products_id ORDER BY p.products_sort_order, pd.products_name';
			$products = $db->Execute($sql);
			if($products->RecordCount() > 0){
				while (!$products->EOF) {
					$this->category_tree[$products->fields['master_categories_id']]['products'][] = 
						array('products_id' 	=> $products->fields['products_id'],
							  'products_name' 	=> $products->fields['products_name']);
					$products->MoveNext();
				}
			}
			$this->is_product_tree_built = true;
		}
	}
}
?>