<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CasUser model table
 */
class CreateCasUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cas_users', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->string('user_uuid')->unique();
            $table->string('uid')->unique();
            $table->integer('cas_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('roles')->nullable();

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
        Schema::dropIfExists('cas_users');
    }
}
