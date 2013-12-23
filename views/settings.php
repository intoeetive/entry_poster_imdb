<?php $this->load->view('tabs'); ?>

<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=entry_poster_imdb'.AMP.'method=save_settings', array('id'=>'entry_poster_imdb_settings_form'));?>


<?php 
$this->table->set_template($cp_pad_table_template); 
$this->table->set_heading(
    array('data' => lang('imdb_field'), 'style' => 'width:50%;'),
    lang('custom_entry_field')
);


foreach ($settings as $key => $val)
{
	$this->table->add_row(lang($key, $key), $val);
}

echo $this->table->generate();

$this->table->clear();
?>


<p><?=form_submit('submit', lang('submit'), 'class="submit"')?></p>

<?php
form_close();

