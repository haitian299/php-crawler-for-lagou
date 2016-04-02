<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/30
 * Time: 下午9:01
 */
class Company extends Model
{
    protected $table = 'lagou_company';

    protected $guarded = [];

    public function industryFields()
    {
        return $this->belongsToMany(
            'App\Models\IndustryField',
            'lagou_company_industry',
            'company_id',
            'industry_field_id'
        );
    }

    public function labels()
    {
        return $this->belongsToMany(
            'App\Models\Label',
            'lagou_company_label_relation',
            'company_id',
            'label_id'
        );
    }
}