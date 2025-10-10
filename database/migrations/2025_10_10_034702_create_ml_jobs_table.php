<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMlJobsTable extends Migration
{
    public function up()
    {
        Schema::create('ml_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->string('status')->default('running');
            $table->json('params')->nullable();
            $table->text('log')->nullable();
            $table->integer('build_number')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ml_jobs');
    }
}