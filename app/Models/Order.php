<?php

namespace App\Models;

use App\Http\Controllers\Traits\FormatDateTrait;
use App\Http\Controllers\Traits\SlugNameTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    use FormatDateTrait;

    protected $table = 'orders';

    protected $fillable = [
        'customer_id',
        'promotion_id',
        'code',
        'note',
        'total_price',
        'price_promotion',
        'branch_id',
        'status',
        'status_delivered',
        'created_at'
    ];

    protected $appends = [
        'formatted_created_at',
        'formatted_updated_at',
    ];

    public $timestamps = true;

    public function alias(){
        return $this->morphOne(Alias::class,'model');
    }

    public function metaseo(){
        return $this->morphOne(MetaSeo::class,'model');
    }

    public function images(){
        return $this->morphMany(Image::class,'model');
    }

    public function customers(){
        return $this->belongsTo(Customer::class,'customer_id','id');
    }

    public function promotions(){
        return $this->belongsTo(Promotion::class,'promotion_id','id');
    }

    public function orderDetails(){
        return $this->hasMany(Order_detail::class);
    }

    public function paids(){
        return $this->hasMany(Payment::class);
    }

    public function getImagesByIndex(array $indexs){
        return $this->images()->whereIn('index', $indexs)->get();
    }
    
}
