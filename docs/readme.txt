This class is very simple to use, you have only a few functions that you can call:
1. Basic:
a. build_category_string($parent_html = 'div', $child_tag = 'span', $divider = '', $categories_id = 0, 
									$max_level = 9, $include_root = false, $strict = false)
									
--> This is the most important function, it will build and return the category string (already in html code) for you, all you have to do is to print it out.
As you can see, the function takes a number of parameters:
Let me draw a sample cat tree so that you can understand it better: (assuming we use the default params)

|<div class="level_0 has_sub">
|--------<span class="level_0 has_sub"> link here
|----------------|<div class="level_1 has_sub">
|----------------|--------<span class="level_1 no_sub">link here</span>
|----------------|--------<span class="level_1 has_sub current">link here</span>
|----------------|----------------|<div class="level_2 has_sub current">
|----------------|----------------|--------<span class="level_2 no_sub"> link here</span>
|----------------|----------------|--------<span class="level_2 no_sub"> link here</span>
|----------------|----------------|</div>
|----------------|</div>
</div>

And it goes on and on.

* $divider will be put between children (sub cats same level), so be careful when you use it along with parent_tag and child_tag, you may have invalid html code.

* $categories: the root of the tree, what it to start else where? pass the cat id there.
For instance, here is the code I used to build a tree based on the current Category current visiting:
if(isset($_GET['cPath']))
$_SESSION['category_tree']->build_category_string('ul', 'li', '', $_GET['cPath'], 2) 

* $include_root: this is interesting, usually when you build a tree, you dont want to include the root of that tree, however, if you want to, set this to true.

* $strict: this is alsu interesting: the category tree will auto expand when you go into deeper sub cat (even if the max_level is exceeded). However, if you want it to always stick to the max level strictly, set this to true.

b. retrieve_cpath($categories_id)

--> The name said it all, you passed in the cat id, you get the whole cPath back, this will even accept cPath (in case you have part of the cPath and want to get back the complete cPath)

c. retrieve_categories_tree_array()

--> return the whole category tree, in case you want to play with it

d. retrieve_deepest_cats_array($categories_id)

--> Very very useful one, this one will return the deepest level sub cats of any given cat id. Given the way ZC works, you know that these cats are the ones that actually whole products. If you want to retrieve all products of a parent cat, this is the one you need.
This function returns an array of cat id

e. build_deepest_level_children()

--> if you want to use the function above(d), make sure you call this one first, this will build the info needed.