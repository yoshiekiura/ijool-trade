<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfilesTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up(): void
  {
    Schema::create('profiles', function (Blueprint $table) {
      $table->uuid("id")->primary();
      $table->unsignedBigInteger("user_id");
      $table->foreign('user_id')->references('id')->on('users');
      $table->text("image")->nullable();
      $table->text("country")->nullable();
      $table->text("city")->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down(): void
  {
    Schema::dropIfExists('profiles');
  }
}
