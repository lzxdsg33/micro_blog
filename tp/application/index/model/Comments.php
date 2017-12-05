<?php
namespace app\index\model;

use think\Model;

class Comments extends Model
{
	protected $name = 'comments';

	public function date_convert()
	{
		return date('Y-m-d H:i:s', $this->timestamp);
	}

}

?>