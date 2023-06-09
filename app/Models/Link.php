<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class Link extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
	protected $collection = 'weblinks';

    protected $fillable = ['web_domain', 'web_link'];
}
