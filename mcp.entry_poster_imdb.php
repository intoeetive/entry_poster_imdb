<?php

/*
=====================================================
 Entry Poster using IMDB
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2013-2014 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'entry_poster_imdb/config.php';

class Entry_poster_imdb_mcp {

    var $version = ENTRY_POSTER_IMDB_ADDON_VERSION;
    
    var $settings = array();
    
    var $mimes = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        );

    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    
    function post_entry()
    {
    	$this->EE->load->library('imdb');
        
        if($this->EE->input->get_post('imdb_id')=='') 
    	{
    		show_error($this->EE->lang->line('unauthorized_access'));
    	}
        
        $settings_q = $this->EE->db->select('settings')
                            ->from('modules')
                            ->where('module_name','Entry_poster_imdb')
                            ->limit(1)
                            ->get(); 
        $settings = unserialize($settings_q->row('settings'));
        
        if ($settings['channel_to_post']=='')
        {
            show_error(lang('provide_settings'));
        }
        else
        {
            $channel_id = $settings['channel_to_post'];
        }

        $movie = $this->EE->imdb->getMovieInfo($this->EE->input->get_post('imdb_id')); 
        if (isset($movie['error']) && $movie['error']!='')
        {
            show_error($movie['error']);
        }
        
        $this->EE->load->library('api'); 
        $this->EE->api->instantiate('channel_entries'); 
        $this->EE->api->instantiate('channel_fields'); 
        
        $data = array( 
                'title' => $movie['title'], 
                'entry_date' => ($settings['entry_date']=='release_date')?strtotime($movie['release_date']):$this->EE->localize->now,
                'author_id' => $this->EE->session->userdata('member_id'),
                'channel_id'=> $channel_id,
                'status'	=> 'open'
            ); 
            
        unset($settings['channel_to_post']);
        unset($settings['entry_date']);
        
        $tags = array('genres', 'directors', 'writers', 'stars');
        $dates = array('release_date');
        $images = array('poster', 'poster_large', 'media_images');
        $ci_fields = array();
        foreach ($settings as $key=>$val)
        {
            if ($val!='')
            {
                if (in_array($key, $dates))
                {
                    $data['field_id_'.$val] = strtotime($movie[$key]);
                }
                elseif (in_array($key, $images))
                {
                    $data['field_id_'.$val] = 'ChannelImages';
                }
                elseif (in_array($key, $tags))
                {
                    $data['field_id_'.$val] = '';
                    foreach ($movie[$key] as $item)
                    {
                        $data['field_id_'.$val] .= $item."\n";
                    }
                    $movie[$key] = trim($data['field_id_'.$val]);
                }
                else
                {
                    $data['field_id_'.$val] = $movie[$key];
                }
                $data['field_ft_'.$val] = 'none';
            }
        }
        
        $this->EE->api_channel_fields->setup_entry_settings($channel_id, $data); 
        $this->EE->api_channel_entries->save_entry($data, $channel_id);
        
        //posters processing
        $entry_id_q = $this->EE->db->select('entry_id')
                    ->from('channel_titles')
                    ->where('title', $data['title'])
                    ->where('channel_id', $channel_id)
                    ->order_by('entry_date', 'desc')
                    ->limit(1)
                    ->get();
        $entry_id = $entry_id_q->row('entry_id');
        
        foreach ($tags as $fieldtagname)
        {
            if ($settings[$fieldtagname]!='')
            {
                $field_settings_q = $this->EE->db->select('field_settings')
                                ->from('channel_fields')
                                ->where('field_id', $settings[$fieldtagname])
                                ->get();
                $field_settings = unserialize(base64_decode($field_settings_q->row('field_settings')));
            
                if (!isset($this->tag_ob) || !is_object($this->tag_ob))
        		{
        			require_once PATH_THIRD . 'tag/mod.tag.php';
        		}
                
                $this->tag_ob = new Tag();
                
        		$this->tag_ob->site_id			= $this->EE->config->item('site_id');
        		$this->tag_ob->entry_id			= $entry_id;
        		$this->tag_ob->str				= $movie[$fieldtagname];
        		$this->tag_ob->from_ft			= TRUE;
        		$this->tag_ob->field_id			= $settings[$fieldtagname];
        		$this->tag_ob->tag_group_id		= $field_settings['tag_group'];
        		$this->tag_ob->type				= 'channel';
        		//everything is stored hidden as newline separation
        		$this->tag_ob->separator_override = 'newline';
        
        		$this->tag_ob->parse();
            }
        }
		
        
        
        if (isset($settings['poster']) && $settings['poster']!='' && $movie['poster']!='')
        {		
            $this->_insert_images($channel_id, $entry_id, $settings['poster'], $movie['poster'], $data); 
        }
        if (isset($settings['poster_large']) && $settings['poster_large']!='' && $movie['poster_large']!='')
        {		
            $this->_insert_images($channel_id, $entry_id, $settings['poster_large'], $movie['poster_large'], $data); 
        }
        if (isset($settings['media_images']) && $settings['media_images']!='' && $movie['media_images']!='')
        {		
            foreach ($movie['media_images'] as $media_image)
            {
                $this->_insert_images($channel_id, $entry_id, $settings['media_images'], $media_image, $data);
            } 
        }
        
        $this->EE->session->set_flashdata(
    		'message_success',
    	 	$this->EE->lang->line('entry_created')
    	);
        
        $this->EE->functions->redirect(BASE.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'channel_id='.$channel_id.AMP.'entry_id='.$entry_id);

    }
    
    
    
    function _insert_images($channel_id, $entry_id, $field_id, $url, $parent_data)
    {
        $this->EE->load->library('filemanager');
        
        
        $field_settings_q = $this->EE->db->select('field_settings')
                            ->from('channel_fields')
                            ->where('field_id', $field_id)
                            ->get();
        $field_settings = unserialize(base64_decode($field_settings_q->row('field_settings')));
        
        $dir_id = $field_settings['channel_images']['locations']['local']['location'];
        
        $dir = $this->EE->filemanager->fetch_upload_dir_prefs($dir_id);
        
        $filename_a = explode("/", $url);
        $filename = end($filename_a);
        //move images
        @mkdir($dir['server_path'].$entry_id, 0777);
        @copy($url, $dir['server_path'].$entry_id.'/'.$filename);
        //prepare data
        $ext = substr($filename, strrpos($filename, '.')+1);
        
        $insert = array(
            'entry_id' => $entry_id,
            'site_id' => $this->EE->config->item('site_id'),
            'channel_id' => $channel_id,
            'member_id' => $this->EE->session->userdata('member_id'),
            'field_id' => $field_id,
            'filename' => $filename,
            'extension' => $ext,
            'mime' => $this->mimes["$ext"],
            'upload_date' => $parent_data['entry_date'],
            'title'	=> $parent_data['title']
        );
        
        $this->EE->db->insert('channel_images', $insert);
        
        if (class_exists('Channel_Images_API') != TRUE) include PATH_THIRD.'channel_images/api.channel_images.php';
		$API = new Channel_Images_API();

        $API->run_actions($filename, $field_id, $dir['server_path'].$entry_id.'/');

    }
    
    
    
    
    function index()
    {
        $settings_q = $this->EE->db->select('settings')
                            ->from('modules')
                            ->where('module_name','Entry_poster_imdb')
                            ->limit(1)
                            ->get(); 
        if ($settings_q->row('settings')!='')
        {
            return $this->search();
        }
        else
        {
            return $this->settings();
        }
    }
    
    
    function settings()
    {

        $this->EE->load->helper('form');
        $this->EE->load->library('table');  
        
        $site_id = $this->EE->config->item('site_id');
        
        $settings = array(
            'channel_to_post'   => '',
            'entry_date'   => 'release_date',
            'imdb_url'  => '',
            'year'      => '',
            'rating'    => '',
            'runtime'   => '',
            'genres'    => '',
            'directors' => '',
            'writers'   => '',
            'mpaa_rating'   => '',
            'release_date'  => '',
            'plot'      => '',
            'stars'     => '',
            'storyline' => '',
            'poster'    => '',
            'poster_large'  => '',
            'media_images' => '',
            'trailer'   => ''
        );

        $settings_q = $this->EE->db->select('settings')
                            ->from('modules')
                            ->where('module_name','Entry_poster_imdb')
                            ->limit(1)
                            ->get(); 
        if ($settings_q->num_rows()>0) 
        {
            $saved_settings = unserialize($settings_q->row('settings'));
            foreach ($saved_settings as $key=>$val)
            {
                $settings[$key] = $val;
            }
        }

        $vars = array();

        
        $this->EE->db->select('field_id, field_name, field_label')
                    ->from('exp_channel_fields');
        $cq = $this->EE->db->get();
        $fields = array();
        $fields[''] = '-';
        foreach ($cq->result_array() as $row)
        {
            $fields[$row['field_id']] = $row['field_label'];
        }
        
        foreach ($settings as $key=>$val)
        {
            $vars['settings'][$key] = form_dropdown($key, $fields, $val);
        }
        
        $channels = array();
        $channels[''] = '-';
        $this->EE->db->select('channel_id, channel_title');
        $this->EE->db->where('site_id', $this->EE->config->item('site_id'));
        $channels_q = $this->EE->db->get('channels');
        foreach($channels_q->result_array() as $channel)
        {
            $channels[$channel['channel_id']] = $channel['channel_title'];
        }
        $vars['settings']['channel_to_post'] = form_dropdown('channel_to_post', $channels, $settings['channel_to_post']);
        
        $vars['settings']['entry_date'] = form_dropdown('entry_date', array('release_date'=>lang('release_date'), 'current_time'=>lang('current_time')), $settings['entry_date']);

    	return $this->EE->load->view('settings', $vars, TRUE);			
    }
    
    
    
    
    function save_settings()
    {
    	if (empty($_POST))
    	{
    		show_error($this->EE->lang->line('unauthorized_access'));
    	}
        
        unset($_POST['submit']);
       
        $upd_data = array('settings' => serialize($_POST));

        $this->EE->db->where('module_name','Entry_poster_imdb');
    	$this->EE->db->update('modules', $upd_data);        
    	
    	$this->EE->session->set_flashdata(
    		'message_success',
    	 	$this->EE->lang->line('preferences_updated')
    	);
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=entry_poster_imdb'.AMP.'method=settings');
    }
    
    
    function search()
    {
        return $this->EE->load->view('search', array(), TRUE);	
    }
    
    
    function search_results()
    {
        //Load the scraper library
        $this->EE->load->library('imdb');
        
        //Check if the query is submitted
        if($this->EE->input->get_post('query')!='') 
        {
            $data['movie'] = $this->EE->imdb->getMovieInfo($this->EE->input->get_post('query')); //Get the movie information
        }
        
        //Load the view and pass along the data
        return $this->EE->load->view('search', $data, TRUE);	
    }

  

}
/* END */
?>