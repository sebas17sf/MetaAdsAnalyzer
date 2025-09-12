<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    protected $table = 'FileUploads';

    protected $fillable = [
        'FileName',
        'UploadedBy',
        'UploadedAt',
        'RecordsProcessed',
        'Status'
    ];

    public $timestamps = false;
}
