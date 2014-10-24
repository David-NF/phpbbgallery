<?php
/**
*
* @package phpBB Gallery
* @version $Id$
* @copyright (c) 2014 Stanislav Atanasov Lucifer@anavaro.com http://www.anavaro.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace phpbbgallery\core\upload;

/**
* Responsible for holding all file relevant information, as well as doing file-specific operations.
* The {@link fileupload fileupload class} can be used to upload several files, each of them being this object to operate further on.
*/
class filespec
{
	var $filename = '';
	var $realname = '';
	var $uploadname = '';
	var $mimetype = '';
	var $extension = '';
	var $filesize = 0;
	var $width = 0;
	var $height = 0;
	var $image_info = array();

	var $destination_file = '';
	var $destination_path = '';

	var $file_moved = false;
	var $init_error = false;
	var $local = false;

	var $error = array();

	var $upload = '';

	/**
	 * The plupload object
	 * @var \phpbb\plupload\plupload
	 */
	protected $plupload;

	/**
	 * phpBB Mimetype guesser
	 * @var \phpbb\mimetype\guesser
	 */
	protected $mimetype_guesser;

	/**
	* File Class
	* @access private
	*/
	function filespec($upload_ary, $upload_namespace, \phpbb\mimetype\guesser $mimetype_guesser = null, \phpbb\plupload\plupload $plupload = null)
	{
		if (!isset($upload_ary))
		{
			$this->init_error = true;
			return;
		}
		$this->filename = $upload_ary['tmp_name'];
		$this->filesize = $upload_ary['size'];
		$name = (STRIP) ? stripslashes($upload_ary['name']) : $upload_ary['name'];
		$name = trim(utf8_basename($name));
		$this->realname = $this->uploadname = $name;
		$this->mimetype = $upload_ary['type'];

		// Opera adds the name to the mime type
		$this->mimetype	= (strpos($this->mimetype, '; name') !== false) ? str_replace(strstr($this->mimetype, '; name'), '', $this->mimetype) : $this->mimetype;

		if (!$this->mimetype)
		{
			$this->mimetype = 'application/octet-stream';
		}

		$this->extension = strtolower(self::get_extension($this->realname));

		// Try to get real filesize from temporary folder (not always working) ;)
		$this->filesize = (@filesize($this->filename)) ? @filesize($this->filename) : $this->filesize;

		$this->width = $this->height = 0;
		$this->file_moved = false;

		$this->local = (isset($upload_ary['local_mode'])) ? true : false;
		$this->upload = $upload_namespace;
		$this->plupload = $plupload;
		$this->mimetype_guesser = $mimetype_guesser;
	}

	/**
	* Cleans destination filename
	*
	* @param real|unique|unique_ext $mode real creates a realname, filtering some characters, lowering every character. Unique creates an unique filename
	* @param string $prefix Prefix applied to filename
	* @param string $user_id The user_id is only needed for when cleaning a user's avatar
	* @access public
	*/
	function clean_filename($mode = 'unique', $prefix = '', $user_id = '')
	{
		if ($this->init_error)
		{
			return;
		}

		switch ($mode)
		{
			case 'real':
				// Remove every extension from filename (to not let the mime bug being exposed)
				if (strpos($this->realname, '.') !== false)
				{
					$this->realname = substr($this->realname, 0, strpos($this->realname, '.'));
				}

				// Replace any chars which may cause us problems with _
				$bad_chars = array("'", "\\", ' ', '/', ':', '*', '?', '"', '<', '>', '|');

				$this->realname = rawurlencode(str_replace($bad_chars, '_', strtolower($this->realname)));
				$this->realname = preg_replace("/%(\w{2})/", '_', $this->realname);

				$this->realname = $prefix . $this->realname . '.' . $this->extension;
			break;

			case 'unique':
				$this->realname = $prefix . md5(unique_id());
			break;

			case 'avatar':
				$this->extension = strtolower($this->extension);
				$this->realname = $prefix . $user_id . '.' . $this->extension;

			break;

			case 'unique_ext':
			default:
				$this->realname = $prefix . md5(unique_id()) . '.' . $this->extension;
			break;
		}
	}

	/**
	* Get property from file object
	*/
	function get($property)
	{
		if ($this->init_error || !isset($this->$property))
		{
			return false;
		}

		return $this->$property;
	}

	/**
	* Check if file is an image (mimetype)
	*
	* @return true if it is an image, false if not
	*/
	function is_image()
	{
		return (strpos($this->mimetype, 'image/') === 0);
	}

	/**
	* Check if the file got correctly uploaded
	*
	* @return true if it is a valid upload, false if not
	*/
	function is_uploaded()
	{
		$is_plupload = $this->plupload && $this->plupload->is_active();

		if (!$this->local && !$is_plupload && !is_uploaded_file($this->filename))
		{
			return false;
		}

		if (($this->local || $is_plupload) && !file_exists($this->filename))
		{
			return false;
		}

		return true;
	}

	/**
	* Remove file
	*/
	function remove()
	{
		if ($this->file_moved)
		{
			@unlink($this->destination_file);
		}
	}

	/**
	* Get file extension
	*
	* @param string Filename that needs to be checked
	* @return string Extension of the supplied filename
	*/
	static public function get_extension($filename)
	{
		if (strpos($filename, '.') === false)
		{
			return '';
		}

		$filename = explode('.', $filename);
		return array_pop($filename);
	}

	/**
	* Get mimetype
	*
	* @param string $filename Filename that needs to be checked
	* @return string Mimetype of supplied filename
	*/
	function get_mimetype($filename)
	{
		if ($this->mimetype_guesser !== null)
		{
			$mimetype = $this->mimetype_guesser->guess($filename, $this->uploadname);

			if ($mimetype !== 'application/octet-stream')
			{
				$this->mimetype = $mimetype;
			}
		}

		return $this->mimetype;
	}

	/**
	* Get filesize
	*/
	function get_filesize($filename)
	{
		return @filesize($filename);
	}


	/**
	* Check the first 256 bytes for forbidden content
	*/
	function check_content($disallowed_content)
	{
		if (empty($disallowed_content))
		{
			return true;
		}

		$fp = @fopen($this->filename, 'rb');

		if ($fp !== false)
		{
			$ie_mime_relevant = fread($fp, 256);
			fclose($fp);
			foreach ($disallowed_content as $forbidden)
			{
				if (stripos($ie_mime_relevant, '<' . $forbidden) !== false)
				{
					return false;
				}
			}
		}
		return true;
	}

	/**
	* Move file to destination folder
	* The phpbb_root_path variable will be applied to the destination path
	*
	* @param string $destination Destination path, for example $config['avatar_path']
	* @param bool $overwrite If set to true, an already existing file will be overwritten
	* @param bool $skip_image_check If set to true, the check for the file to be a valid image is skipped
	* @param string $chmod Permission mask for chmodding the file after a successful move. The mode entered here reflects the mode defined by {@link phpbb_chmod()}
	*
	* @access public
	*/
	function move_file($destination, $overwrite = false, $skip_image_check = false, $chmod = false)
	{
		global $user, $phpbb_root_path;

		if (sizeof($this->error))
		{
			return false;
		}

		$chmod = ($chmod === false) ? CHMOD_READ | CHMOD_WRITE : $chmod;

		// We need to trust the admin in specifying valid upload directories and an attacker not being able to overwrite it...
		$this->destination_path = $phpbb_root_path . $destination;

		// Check if the destination path exist...
		if (!file_exists($this->destination_path))
		{
			@unlink($this->filename);
			return false;
		}

		$upload_mode = (@ini_get('open_basedir') || @ini_get('safe_mode') || strtolower(@ini_get('safe_mode')) == 'on') ? 'move' : 'copy';
		$upload_mode = ($this->local) ? 'local' : $upload_mode;
		$this->destination_file = $this->destination_path . '/' . utf8_basename($this->realname);

		// Check if the file already exist, else there is something wrong...
		if (file_exists($this->destination_file) && !$overwrite)
		{
			@unlink($this->filename);
			$this->error[] = $user->lang($this->upload->error_prefix . 'GENERAL_UPLOAD_ERROR', $this->destination_file);
			$this->file_moved = false;
			return false;
		}
		else
		{
			if (file_exists($this->destination_file))
			{
				@unlink($this->destination_file);
			}

			switch ($upload_mode)
			{
				case 'copy':

					if (!@copy($this->filename, $this->destination_file))
					{
						if (!@move_uploaded_file($this->filename, $this->destination_file))
						{
							$this->error[] = sprintf($user->lang[$this->upload->error_prefix . 'GENERAL_UPLOAD_ERROR'], $this->destination_file);
						}
					}

				break;

				case 'move':

					if (!@move_uploaded_file($this->filename, $this->destination_file))
					{
						if (!@copy($this->filename, $this->destination_file))
						{
							$this->error[] = sprintf($user->lang[$this->upload->error_prefix . 'GENERAL_UPLOAD_ERROR'], $this->destination_file);
						}
					}

				break;

				case 'local':

					if (!@copy($this->filename, $this->destination_file))
					{
						$this->error[] = sprintf($user->lang[$this->upload->error_prefix . 'GENERAL_UPLOAD_ERROR'], $this->destination_file);
					}

				break;
			}

			// Remove temporary filename
			@unlink($this->filename);

			if (sizeof($this->error))
			{
				return false;
			}

			phpbb_chmod($this->destination_file, $chmod);
		}

		// Try to get real filesize from destination folder
		$this->filesize = (@filesize($this->destination_file)) ? @filesize($this->destination_file) : $this->filesize;

		// Get mimetype of supplied file
		$this->mimetype = $this->get_mimetype($this->destination_file);

		if ($this->is_image() && !$skip_image_check)
		{
			$this->width = $this->height = 0;

			if (($this->image_info = @getimagesize($this->destination_file)) !== false)
			{
				$this->width = $this->image_info[0];
				$this->height = $this->image_info[1];

				if (!empty($this->image_info['mime']))
				{
					$this->mimetype = $this->image_info['mime'];
				}

				// Check image type
				$types = fileupload::image_types();

				if (!isset($types[$this->image_info[2]]) || !in_array($this->extension, $types[$this->image_info[2]]))
				{
					if (!isset($types[$this->image_info[2]]))
					{
						$this->error[] = sprintf($user->lang['IMAGE_FILETYPE_INVALID'], $this->image_info[2], $this->mimetype);
					}
					else
					{
						$this->error[] = sprintf($user->lang['IMAGE_FILETYPE_MISMATCH'], $types[$this->image_info[2]][0], $this->extension);
					}
				}

				// Make sure the dimensions match a valid image
				if (empty($this->width) || empty($this->height))
				{
					$this->error[] = $user->lang['ATTACHED_IMAGE_NOT_IMAGE'];
				}
			}
			else
			{
				$this->error[] = $user->lang['UNABLE_GET_IMAGE_SIZE'];
			}
		}

		$this->file_moved = true;
		$this->additional_checks();
		unset($this->upload);

		return true;
	}

	/**
	* Performing additional checks
	*/
	function additional_checks()
	{
		global $user;

		if (!$this->file_moved)
		{
			return false;
		}

		// Filesize is too big or it's 0 if it was larger than the maxsize in the upload form
		if ($this->upload->max_filesize && ($this->get('filesize') > $this->upload->max_filesize || $this->filesize == 0))
		{
			$max_filesize = get_formatted_filesize($this->upload->max_filesize, false);

			$this->error[] = sprintf($user->lang[$this->upload->error_prefix . 'WRONG_FILESIZE'], $max_filesize['value'], $max_filesize['unit']);

			return false;
		}

		if (!$this->upload->valid_dimensions($this))
		{
			$this->error[] = $user->lang($this->upload->error_prefix . 'WRONG_SIZE',
				$user->lang('PIXELS', (int) $this->upload->min_width),
				$user->lang('PIXELS', (int) $this->upload->min_height),
				$user->lang('PIXELS', (int) $this->upload->max_width),
				$user->lang('PIXELS', (int) $this->upload->max_height),
				$user->lang('PIXELS', (int) $this->width),
				$user->lang('PIXELS', (int) $this->height));

			return false;
		}

		return true;
	}
}

/**
* Class for assigning error messages before a real filespec class can be assigned
*/
class fileerror extends filespec
{
	function fileerror($error_msg)
	{
		$this->error[] = $error_msg;
	}
}
