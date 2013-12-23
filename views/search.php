<?php $this->load->view('tabs'); ?>

<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=entry_poster_imdb'.AMP.'method=search_results', array('id'=>'entry_poster_imdb_search_form'));?>


<p><input type="text" style="width: 60%" name="query" /></p>


<p><?=form_submit('submit', lang('submit'), 'class="submit"')?></p>

<?php
form_close();
?>

<?php
if(isset($movie)){
    if (isset($movie['error']) && $movie['error']!='')
    {
        echo '<p style="color:red">'.$movie['error'].'</p>';
    }
    else
    {
        
?>

<p><a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=entry_poster_imdb'.AMP.'method=post_entry'.AMP.'imdb_id='.$movie['title_id']?>"><?=lang('post_entry')?></a></p>

<p><strong><?=$movie['title']?></strong></p>

<p><?=$movie['year']?></p>

<p><?=$movie['plot']?></p>

<img src="<?=$movie['poster']?>" />

<?php        
        
    }
} 
?>



