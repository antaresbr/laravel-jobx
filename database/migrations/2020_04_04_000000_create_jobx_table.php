<?php

use Antares\Jobx\Handlers\JobxDbHandler;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateJobxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jobx', function (Blueprint $table) {
            $tsPrecision = config('jobx.timestamp_precision');
            $currentTimestamp = JobxDbHandler::getCurrentTimestamp();

            $table->id();
            $table->string('job_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('status');
            $table->timestamp('created_at', $tsPrecision)->default(DB::raw($currentTimestamp));
            $table->timestamp('updated_at', $tsPrecision)->default(DB::raw($currentTimestamp));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jobx');
    }
}
