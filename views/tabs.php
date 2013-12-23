<ul class="tab_menu" id="tab_menu_tabs">
<li class="content_tab<?php if (in_array($this->input->get('method'), array('', 'index', 'settings'))) echo ' current';?>"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=entry_poster_imdb'.AMP.'method=settings'?>"><?=lang('settings')?></a>  </li> 
<li class="content_tab<?php if (in_array($this->input->get('method'), array('search', 'search_results'))) echo ' current';?>"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=entry_poster_imdb'.AMP.'method=search'?>"><?=lang('search_movie')?></a>  </li> 


</ul> 
<div class="clear_left shun"></div>