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

        $movie = $this->EE->imdb->getMovieInfo('tt'.$this->EE->input->get_post('imdb_id')); 
        if ($movie['error']!='')
        {
            show_error($movie['error']);
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
        
        $this->EE->load->library('api'); 
        $this->EE->api->instantiate('channel_entries'); 
        $this->EE->api->instantiate('channel_fields'); 
        
        $data = array( 
                'title' => $movie['title'], 
                'entry_date' => $this->EE->localize->now,
                'author_id' => $this->EE->session->userdata('member_id'),
                'channel_id'=> $channel_id,
                'status'	=> 'open'
            ); 
            
        unset($settings['channel_to_post']);
        
        $tags = array('genres', 'directors', 'writers');
        $dates = array('release_date');
        $images = array('poster', 'poster_large');
        $ci_fields = array();
        foreach ($settings as $key=>$val)
        {
            if (in_array($key, $dates))
            {
                
            }
            elseif (in_array($key, $images))
            {
                $data['field_id_'.$val] = 'ChannelImages';
            }
            elseif (in_array($key, $tags))
            {
                $data['field_id_'.$val] = '';
            }
            else
            {
                $data['field_id_'.$val] = $movie[$key];
            }
            $data['field_ft_'.$val] = 'none';
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
        
        $mimes = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        );
		
        if ($movie['poster']!='')
        {		
    		$new_filename = strtolower($row['filename'].$row['extension']);
            //move images
            @mkdir($new_directory.'/'.$entry_id, 0777);
            @copy($this->EE->functions->remove_double_slashes('/var/www/vhosts/kazantip.com/httpdocs/images/pics/beauties/'.$row['filename'].$row['extension']), $new_directory.'/'.$entry_id.'/'.$new_filename);
            //prepare data
            $ext = substr($new_filename, strrpos($new_filename, '.')+1);
            
            $insert = array(
                'entry_id' => $entry_id,
                'site_id' => $this->EE->config->item('site_id'),
                'channel_id' => $channel_id,
                'member_id' => $this->EE->session->userdata('member_id'),
                'field_id' => $settings['poster'],
                'filename' => $filename,
                'extension' => $ext,
                'mime' => $mimes["$ext"],
                'upload_date' => $data['entry_date'],
                'title'	=> $data['title']
            );
            
            $this->EE->db->insert('channel_images', $insert);
        }

 
        foreach ($members as $key => $member_id)
		{
            $this->EE->db->select('entry_id')
                    ->from('channel_titles')
                    ->where('channel_id', $this->settings['channel'])
                    ->where('author_id', $member_id);
            $q = $this->EE->db->get();
            if ($q->num_rows()>0)
            {
                continue;
            }
            
            $this->EE->db->select('exp_members.screen_name, exp_members.bio, exp_members.email, exp_member_data.*')
                        ->from('exp_members')
                        ->join('exp_member_data', 'exp_members.member_id=exp_member_data.member_id', 'left')
                        ->where('exp_members.member_id', $member_id);
            $q = $this->EE->db->get();
            
            
            /*
            foreach ($this->settings as $key=>$setting)
            {
                if ($setting!='')
                {
                    $m_field_id = array_search($key, $member_fields);
                    if ($m_field_id!==false)
                    {
                        $data['field_id_'.$setting] = $q->row('m_field_id_'.$m_field_id);
                        $data['field_ft_'.$setting] = 'none';
                    }
                    if (in_array($key, array('bio', 'email')))
                    {
                        $data['field_id_'.$setting] = $q->row($key);
                        $data['field_ft_'.$setting] = 'none';
                    }
                }
            }
*/
            
            $result = $this->EE->api_channel_entries->submit_new_entry($this->settings['channel'], $data); 
            
        }
    }
    
    
    function index()
    {
        return $this->settings();
    }
    
    
    function settings()
    {

        $this->EE->load->helper('form');
        $this->EE->load->library('table');  
        
        $site_id = $this->EE->config->item('site_id');
        
        $settings = array(
            'channel_to_post'   => '',
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