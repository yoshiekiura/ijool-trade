<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingSubscribesTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('setting_subscribes', function (Blueprint $table) {
      $table->id();
      $table->string("price")->default(5000000000);
      $table->string("idr")->default(150000);
      $table->double("share")->default(0.2);
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('setting_subscribes');
  }
}
