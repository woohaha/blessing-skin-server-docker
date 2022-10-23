<?php

use Illuminate\Database\Migrations\Migration;

class ImportOptions extends Migration
{
    public function up()
    {
        // import options
        $options = config('options');

        $options['version'] = config('app.version');

        $options['announcement'] = str_replace(
            '{version}',
            $options['version'],
            $options['announcement']
        );

        $options['copyright_text'] = str_replace(
            '{year}',
            Carbon\Carbon::now()->year,
            $options['copyright_text']
        );

        foreach ($options as $key => $value) {
            Option::set($key, $value);
        }
    }

    public function down()
    {
        DB::table('options')->delete();
    }
}
