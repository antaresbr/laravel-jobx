<?php

namespace Antares\Jobx\Models;

use Antares\Jobx\Database\Factories\JobxFactory;
use Antares\Socket\Socket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JobxModel extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return JobxFactory::new();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'jobx';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat;

    /**
     * @see Illuminate\Database\Eloquent\Model
     */
    public function __construct(array $attributes = [])
    {
        $this->dateFormat = config('jobx.date_format');

        parent::__construct($attributes);
    }

    /**
     * Synchronize data model with socket
     *
     * @param \Antares\Socket\Socket $socket
     * @param bool $save
     * @return static 
     */
    public function syncWithSocket(?Socket $socket = null, bool $save = true) {
        if (is_null($socket) and !is_null($this->job_id)) {
            $socket = Socket::createFromId($this->job_id);
        }

        if (!is_null($socket)) {
            $this->job_id = $socket->get('id');
            $this->user_id = $socket->get('user');
            $this->status = $socket->get('status');
            $this->seen = $socket->get('seen');
            $this->created_at = $socket->get('created');
            if ($save and $this->isDirty()) {
                $this->save();
            }
        }

        return $this;
    }

    /**
     * Create a new model from socket
     *
     * @param \Antares\Socket\Socket
     * @return static 
     */
    public static function fromSocket(?Socket $socket) {
        if (is_null($socket)) {
            return null;
        }

        /** @var static */
        $instance = static::where('job_id', $socket->get('id'))->first();
        if (!$instance) {
            $instance = new static();
        }
        $instance->syncWithSocket($socket);

        return $instance;
    }

}
