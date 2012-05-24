<?php
/**
 *	MyAlerts Core Plugin File
 *
 *	A simple notification/alert system for MyBB
 *
 *	@author Euan T. <euan@euantor.com>
 *	@version 0.01
 *	@package MyAlerts
 */

if (!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

define('MYALERTS_PLUGIN_PATH', MYBB_ROOT.'inc/plugins/MyAlerts/');

if(!defined("PLUGINLIBRARY"))
{
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

function myalerts_info()
{
    return array(
        'name'          =>  'MyAlerts',
        'description'   =>  'A simple notifications/alerts system for MyBB',
        'website'       =>  'http://euantor.com',
        'author'        =>  'euantor',
        'authorsite'    =>  '',
        'version'       =>  '0.01',
        'guid'          =>  '',
        'compatibility' =>  '16*',
        );
}

function myalerts_install()
{
    global $db, $cache;

    $plugin_info = myalerts_info();
    $euantor_plugins = $cache->read('euantor_plugins');
    $euantor_plugins['myalerts'] = array(
        'title'     =>  'MyAlerts',
        'version'   =>  $plugin_info['version'],
        );
    $cache->update('euantor_plugins', $euantor_plugins);

    if (!$db->table_exists('alerts'))
    {
        $db->write_query('CREATE TABLE `'.TABLE_PREFIX.'alerts` (
            `id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `uid` INT(10) NOT NULL,
            `unread` TINYINT(4) NOT NULL DEFAULT \'1\',
            `dateline` BIGINT(30) NOT NULL,
            `type` VARCHAR(25) NOT NULL,
            `content` TEXT NOT NULL
            ) ENGINE=MyISAM '.$db->build_create_table_collation().';');
    }
}

function myalerts_is_installed()
{
    global $db;
    return $db->table_exists('alerts');
}

function myalerts_uninstall()
{
    global $db;

    if ($db->table_exists('alerts'))
    {
        $db->write_query('DROP TABLE '.TABLE_PREFIX.'alerts');
    }
}

function myalerts_activate()
{
    global $mybb, $db, $lang;

    if (!$lang->myalerts)
    {
        $lang->load('myalerts');
    }

    if(!file_exists(PLUGINLIBRARY))
    {
        flash_message($lang->myalerts_pluginlibrary_missing, "error");
        admin_redirect("index.php?module=config-plugins");
    }

    $this_version = myalerts_info();
    $this_version = $this_version['version'];
    require_once MYALERTS_PLUGIN_PATH.'/Alerts.class.php';

    if (Alerts::getVersion() != $this_version)
    {
        flash_message($lang->sprintf($lang->myalerts_class_outdated, $this_version, Alerts::getVersion()), "error");
        admin_redirect("index.php?module=config-plugins");
    }

    global $PL;
    $PL or require_once PLUGINLIBRARY;

    $PL->settings('myalerts',
    	$lang->setting_group_myalerts,
    	$lang->setting_group_myalerts_desc,
    	array(
    		'enabled'	=>	array(
    			'title'			=>	$lang->setting_myalerts_enabled,
    			'description'	=>	$lang->setting_myalerts_enabled_desc,
    			'value'			=>	'1',
    			),
            'perpage'   =>  array(
                'title'         =>  $lang->setting_myalerts_perpage,
                'description'   =>  $lang->setting_myalerts_perpage_desc,
                'value'         =>  '10',
                'optionscode'   =>  'text',
                ),
            'alert_rep' =>  array(
                'title'         =>  $lang->setting_myalerts_alert_rep,
                'description'   =>  $lang->setting_myalerts_alert_rep_desc,
                'value'         =>  '1',
                ),
            'alert_pm'  =>  array(
                'title'         =>  $lang->setting_myalerts_alert_pm,
                'description'   =>  $lang->setting_myalerts_alert_pm_desc,
                'value'         =>  '1',
                ),
            'alert_buddylist'  =>  array(
                'title'         =>  $lang->setting_myalerts_alert_buddylist,
                'description'   =>  $lang->setting_myalerts_alert_buddylist_desc,
                'value'         =>  '1',
                ),
            'alert_quoted'  =>  array(
                'title'         =>  $lang->setting_myalerts_alert_quoted,
                'description'   =>  $lang->setting_myalerts_alert_quoted_desc,
                'value'         =>  '1',
                ),
            )
    );

$PL->templates('myalerts',
    'MyAlerts',
    array(
        'page'      =>  '<html>
    <head>
        <title>Alerts - {$mybb->settings[\'bbname\']}</title>
        {$headerinclude}
    </head>
    <body>
        {$header}
        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
            <thead>
                <tr>
                    <th class="thead" colspan="1">
                        <strong>Recent Alerts</strong>
                     </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="trow1" id="latestAlertsListing">
                        {$alertsListing}
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="float_right">
            {$multipage}
        </div>
        <br class="clear" />
        {$footer}
    </body>
    </html>',
        'alert_row' =>  '<div class="alert_row">
    {$alertinfo}
</div>',
        )
    );
}

function myalerts_deactivate()
{
	if(!file_exists(PLUGINLIBRARY))
    {
        flash_message("The selected plugin could not be installed because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
        admin_redirect("index.php?module=config-plugins");
    }

    global $PL;
    $PL or require_once PLUGINLIBRARY;

    $PL->settings_delete('myalerts');
    $PL->templates_delete('myalerts');
}

$plugins->add_hook('global_start', 'myalerts_global');
function myalerts_global()
{
	global $db, $mybb;

	if ($mybb->settings['myalerts_enabled'])
	{
		global $Alerts;
		require_once MYALERTS_PLUGIN_PATH.'Alerts.class.php';
		$Alerts = new Alerts($mybb, $db);
	}
}

$plugins->add_hook('reputation_do_add_process', 'myalerts_addAlert_rep');
function myalerts_addAlert_rep()
{
    global $mybb, $reputation;

    if ($mybb->settings['myalerts_enabled'] AND $mybb->settings['myalerts_alert_rep'])
    {
        global $Alerts;

        $Alerts->addAlert($reputation['uid'], 'rep', array(
            'from'      =>  array(
                'uid'       =>  intval($mybb->user['uid']),
                'username'  =>  $mybb->user['username'],
                ),
            )
        );
    }
}

$plugins->add_hook('private_do_send_end', 'myalerts_addAlert_pm');
function myalerts_addAlert_pm()
{
    global $mybb, $pm, $pmhandler;

    if ($mybb->settings['myalerts_enabled'] AND $mybb->settings['myalerts_alert_pm'])
    {
        global $Alerts;

        $Alerts->addAlert($pm['to'], 'pm', array(
            'from'      =>  array(
                'uid'       =>  intval($mybb->user['uid']),
                'username'  =>  $mybb->user['username'],
                ),
            'pm_title'  =>  $pm['subject'],
            'pm_id'     =>  $pmhandler->pmid,
            )
        );
    }
}

$plugins->add_hook('usercp_do_editlists_end', 'myalerts_alert_buddylist');
function myalerts_alert_buddylist()
{
    global $mybb, $db;

    if ($mybb->settings['myalerts_enabled'] AND $mybb->settings['myalerts_alert_buddylist'])
    {
        if ($mybb->input['manage'] != 'ignore') // don't wish to alert when users are added to an ignore list
        {
            global $Alerts;

            $users = explode(",", $mybb->input['add_username']);
            $users = array_map("trim", $users);
            $users = array_unique($users);

            $userArray = array();

            if (count($users) > 0)
            {
                $query = $db->simple_select('users', 'uid', "LOWER(username) IN ('".$db->escape_string(my_strtolower(implode("','", $users)))."')");
            }

            while($user = $db->fetch_array($query))
            {
                $userArray[] = $user['uid'];
            }

            $content = array(
                'from'  =>  array(
                    'uid'       =>  $mybb->user['uid'],
                    'username'  =>  $mybb->user['username'],
                    ),
                );

            $Alerts->addMassAlert($userArray, 'buddylist', $content);
        }
    }
}

$plugins->add_hook('newreply_do_newreply_end', 'myalerts_alert_quoted');
function myalerts_alert_quoted()
{
    global $mybb, $db, $pid, $post;

    if ($mybb->settings['myalerts_enabled'] AND $mybb->settings['myalerts_alert_quoted'])
    {
        global $Alerts;

        $message = $post['message'];

        $pattern = "#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#esi";

        preg_match_all($pattern, $message, $match);
    
        $matches = array_merge($match[2], $match[3]);

        foreach($matches as $key => $value)
        { 
            if (empty($value))
            { 
                unset($matches[$key]); 
            } 
        } 

        $users = array_values($matches);

        foreach ($users as $value)
        {
            $queryArray[] = $db->escape_string($value);
        }

        $uids = $db->write_query('SELECT `uid` FROM `'.TABLE_PREFIX.'users` WHERE username IN (\''.my_strtolower(implode("','", $queryArray)).'\')');

        $userList = array();

        while ($uid = $db->fetch_array($uids))
        {
            $userList[] = $uid['uid'];
        }

        $Alerts->addMassAlert($userList, 'quoted', array(
            'from'      =>  array(
                'uid'       =>  $mybb->user['uid'],
                'username'  =>  $mybb->user['username'],
                ),
            'tid'       =>  $post['tid'],
            'pid'       =>  $pid,
            'subject'   =>  $post['subjct'],
            ));
    }
}

$plugins->add_hook('misc_start', 'myalerts_page');
function myalerts_page()
{
    global $mybb, $db, $lang, $theme, $templates, $headerinclude, $header, $footer;

    if ($mybb->settings['myalerts_enabled'])
    {
        global $Alerts;

        if (!$lang->myalerts)
        {
            $lang->load('myalerts');
        }

        if ($mybb->input['action'] == 'myalerts')
        {
            add_breadcrumb('Alerts', 'misc.php?action=myalerts');

            $numAlerts = $Alerts->getNumAlerts();
            $page = intval($mybb->input['page']);
            $pages = ceil($numAlerts / $mybb->settings['myalerts_perpage']);

            if ($page > $pages OR $page <= 0)
            {
                $page = 1;
            }

            if ($page)
            {
                $start = ($page - 1) * $mybb->settings['myalerts_perpage'];
            }
            else
            {
                $start = 0;
                $page = 1;
            }
            $multipage = multipage($numAlerts, $mybb->settings['myalerts_perpage'], $page, "misc.php?action=myalerts");

            $alertsList = $Alerts->getAlerts($start);

            $readAlerts = array();

            if ($numAlerts > 0)
            {
                foreach ($alertsList as $alert)
                {
                    $alert['user'] = build_profile_link($alert['content']['from']['username'], $alert['content']['from']['uid']);
                    $alert['dateline'] = my_date($mybb->settings['dateformat'], $alert['dateline'])." ".my_date($mybb->settings['timeformat'], $alert['dateline']);

                    if ($alert['type'] == 'rep' AND $mybb->settings['myalerts_alert_rep'])
                    {
                        $alert['message'] = $lang->sprintf($lang->myalerts_rep, $alert['user'], $alert['dateline']);
                    }
                    elseif ($alert['type'] == 'pm' AND $mybb->settings['myalerts_alert_pm'])
                    {
                        $alert['message'] = $lang->sprintf($lang->myalerts_pm, $alert['user'], "<a href=\".{$mybb->settings['bburl']}/private.php?action=read&amp;pmid=".intval($alert['content']['pm_id'])."\">".$alert['content']['pm_title']."</a>", $alert['dateline']);
                    }
                    elseif ($alert['type'] == 'buddylist' AND $mybb->settings['myalerts_alert_buddylist'])
                    {
                        $alert['message'] = $lang->sprintf($lang->myalerts_buddylist, $alert['user'], $alert['dateline']);
                    }
                    elseif ($alert['type'] == 'quoted' AND $mybb->settings['myalerts_alert_quoted'])
                    {
                        $alert['postLink'] = $mybb->settings['bburl'].'/'.get_post_link($alert['content']['pid'], $alert['content']['tid']).'#pid'.$alert['content']['pid'];
                        $alert['message'] = $lang->sprintf($lang->myalerts_quoted, $alert['user'], $alert['postLink'], $alert['dateline']);
                    }

                    $alertinfo = $alert['message'];

                    eval("\$alertsListing .= \"".$templates->get('myalerts_alert_row')."\";");

                    $readAlerts[] = $alert['id'];
                }
            }

            $Alerts->markRead($readAlerts);

            eval("\$content .= \"".$templates->get('myalerts_page')."\";");
            output_page($content);
        }
    }
}

$plugins->add_hook('xmlhttp', 'myalerts_xmlhttp');
function myalerts_xmlhttp()
{
	global $mybb, $db;

	if ($mybb->settings['myalerts_enabled'])
	{
		global $Alerts;

		if ($mybb->input['action'] == 'getAlerts')
		{
			$newAlerts = $Alerts->getAlerts();
			header('Content-Type: text/javascript');
			echo json_encode($newAlerts);
		}

		if ($mybb->input['action'] == 'getNewAlerts')
		{
			$newAlerts = $Alerts->getUnreadAlerts();
			header('Content-Type: text/javascript');
			echo json_encode($newAlerts);
		}

		if ($mybb->input['action'] == 'markAlertsRead')
		{
			if ($Alerts->markRead($db->escape_string($mybb->input['alertsList'])))
			{
				header('Content-Type: text/javascript');
				echo json_encode(array('response' => 'success'));
			}
			else
			{
				header('Content-Type: text/javascript');
				echo json_encode(array('response' => 'error'));
			}
		}

		if ($mybb->input['action'] == 'deleteAlerts')
		{
			if ($Alerts->deleteAlerts($db->escape_string($mybb->input['alertsList'])))
			{
				header('Content-Type: text/javascript');
				echo json_encode(array('response' => 'success'));
			}
			else
			{
				header('Content-Type: text/javascript');
				echo json_encode(array('response' => 'error'));
			}
		}
	}
}
?>