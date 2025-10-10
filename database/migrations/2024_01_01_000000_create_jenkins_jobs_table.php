<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJenkinsJobsTable extends Migration
{
    public function up()
    {
        Schema::create('jenkins_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('last_triggered')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jenkins_jobs');
    }
}
