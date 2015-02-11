<?php
/**
* 
* Gallery Control test
*
* @copyright (c) 2014 Stanislav Atanasov
* @license GNU General Public License, version 2 (GPL-2.0)
* 
* Here we are going to test ACP
*
*/
namespace phpbbgallery\tests\functional;
/**
* @group functional
*/
class phpbbgallery_alpha_test extends phpbbgallery_base
{
	public function install_data()
	{
		return array(
			'core_verview'	=> array(
				'phpbbgallery/core',
				'gallery_acp',
				'adm/index.php?i=-phpbbgallery-core-acp-main_module&mode=overview',
				'ACP_GALLERY_OVERVIEW_EXPLAIN'
			),
			'core_config'	=> array(
				'phpbbgallery/core',
				'gallery_acp',
				'adm/index.php?i=-phpbbgallery-core-acp-config_module&mode=main',
				'GALLERY_CONFIG'
			),
			'core_albums'	=> array(
				'phpbbgallery/core',
				'gallery_acp',
				'adm/index.php?i=-phpbbgallery-core-acp-albums_module&mode=manage',
				'ALBUM_ADMIN'
			),
			'core_perms'	=> array(
				'phpbbgallery/core',
				'gallery_acp',
				'adm/index.php?i=-phpbbgallery-core-acp-permissions_module&mode=manage',
				'PERMISSIONS_EXPLAIN'
			),
			'core_copy_perms'	=> array(
				'phpbbgallery/core',
				'gallery_acp',
				'adm/index.php?i=-phpbbgallery-core-acp-permissions_module&mode=copy',
				'PERMISSIONS_COPY'
			),
			'core_log'	=> array(
				'phpbbgallery/core',
				'info_acp_gallery_logs',
				'adm/index.php?i=-phpbbgallery-core-acp-gallery_logs_module&mode=main',
				'LOG_GALLERY_SHOW_LOGS'
			),
			// This is core, now extensions
			'exif'	=> array(
				'phpbbgallery/exif',
				'exif',
				'adm/index.php?i=-phpbbgallery-core-acp-config_module&mode=main',
				'DISP_EXIF_DATA'
			),
			'acp_cleanup'	=> array(
				'phpbbgallery/acpcleanup',
				'info_acp_gallery_cleanup',
				'adm/index.php?i=-phpbbgallery-acpcleanup-acp-main_module&mode=cleanup',
				'ACP_GALLERY_CLEANUP'
			),
			'acp_import'	=> array(
				'phpbbgallery/acpimport',
				'info_acp_gallery_acpimport',
				'adm/index.php?i=-phpbbgallery-acpimport-acp-main_module&mode=import_images',
				'ACP_IMPORT_ALBUMS'
			),
		);
	}
	/**
	* @dataProvider install_data
	*/
	public function test_install($ext, $lang, $path, $search)
	{
		$this->login();
		$this->admin_login();
		
		$this->add_lang_ext($ext, $lang);
		$crawler = self::request('GET', $path . '&sid=' . $this->sid);
		$this->assertContainsLang($search, $crawler->text());
		
		$this->logout();
		$this->logout();
	}
	// Stop core so we can test if all works with all add-ons off
	public function togle_data()
	{
		return array(
			'core'	=> array('phpbbgallery/core'),
			'exif'	=> array('phpbbgallery/exif'),
			'acpcleanup'	=> array('phpbbgallery/acpcleanup'),
			'acpimport'	=> array('phpbbgallery/acpimport'),
		);
	}
	/**
	* @dataProvider togle_data
	*/
	public function test_stop_core($ext)
	{
		$this->get_db();
		if (strpos($this->db->get_sql_layer(), 'sqlite3') === 0)
		{
			$this->markTestSkipped('There seems to be issue with SQlite and travis about togling');
		}
		$this->login();
		$this->admin_login();
		$this->add_lang_ext('phpbbgallery/core', 'gallery');
		$this->add_lang('common');
		$this->add_lang('acp/extensions');
		
		$crawler = self::request('GET', 'adm/index.php?i=acp_extensions&mode=main&action=disable_pre&ext_name=phpbbgallery%2Fcore&sid=' . $this->sid);
		$form = $crawler->selectButton('disable')->form();
		$crawler = self::submit($form);
		$this->assertContainsLang('EXTENSION_DISABLE_SUCCESS', $crawler->filter('.successbox')->text());
		
		$this->assertEquals(0, $this->get_state($ext));
		
		$crawler = self::request('GET', 'adm/index.php?i=acp_extensions&mode=main&action=enable_pre&ext_name=phpbbgallery%2Fcore&sid=' . $this->sid);
		$form = $crawler->selectButton('enable')->form();
		$crawler = self::submit($form);
		$this->assertContainsLang('EXTENSION_ENABLE_SUCCESS', $crawler->filter('.successbox')->text());
		
		$this->assertEquals(1, $this->get_state($ext));
	}
	// Create album for testing and some users
	public function test_admin_create_album()
	{
		$this->login();
		$this->admin_login();
		
		// Let us create a user we will use for tests
		$this->create_user('testuser1');
		$this->add_user_group('REGISTERED', array('testuser1'));
		// Let me get admin out of registered
		$this->remove_user_group('REGISTERED', array('admin'));
		
		$this->add_lang_ext('phpbbgallery/core', 'gallery_acp');
		$crawler = self::request('GET', 'adm/index.php?i=-phpbbgallery-core-acp-albums_module&mode=manage&sid=' . $this->sid);
		
		// Step 1
		$form = $crawler->selectButton($this->lang('CREATE_ALBUM'))->form();
		$form['album_name'] = 'First test album!';
		$crawler = self::submit($form);
		
		// Step 2 - we should have reached a form for creating album_name
		$this->assertContainsLang('ALBUM_EDIT_EXPLAIN', $crawler->text());
		
		$form = $crawler->selectButton($this->lang('SUBMIT'))->form();
		$crawler = self::submit($form);
		
		// Step 3 - Album should be created and we should have option to add permissions
		$this->assertContainsLang('ALBUM_CREATED', $crawler->text());
		
		$crawler = self::request('GET', 'adm/index.php?i=-phpbbgallery-core-acp-albums_module&mode=manage&sid=' . $this->sid);
		$this->assertContains('First test album!', $crawler->text());
		
		$crawler = self::request('GET', 'app.php/gallery');
		$this->assertContains('First test album!', $crawler->text());
		
		$this->logout();
		$this->logout();
	}
}