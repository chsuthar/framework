<?php

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;

class DatabaseEloquentSoftDeletesIntegrationTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $db = new DB;

        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('email')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->schema()->drop('users');
    }

    /**
     * Tests...
     */
    public function testSoftDeletesAreNotRetrieved()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::all();

        $this->assertCount(1, $users);
        $this->assertEquals(2, $users->first()->id);
        $this->assertNull(SoftDeletesTestUser::find(1));
    }

    public function testWithTrashedReturnsAllRecords()
    {
        $this->createUsers();

        $this->assertCount(2, SoftDeletesTestUser::withTrashed()->get());
        $this->assertInstanceOf(Eloquent::class, SoftDeletesTestUser::withTrashed()->find(1));
    }

    public function testDeleteSetsDeletedColumn()
    {
        $this->createUsers();

        $this->assertInstanceOf(Carbon::class, SoftDeletesTestUser::withTrashed()->find(1)->deleted_at);
        $this->assertNull(SoftDeletesTestUser::find(2)->deleted_at);
    }

    public function testForceDeleteActuallyDeletesRecords()
    {
        $this->createUsers();
        SoftDeletesTestUser::find(2)->forceDelete();

        $users = SoftDeletesTestUser::withTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
    }

    public function testRestoreRestoresRecords()
    {
        $this->createUsers();
        $taylor = SoftDeletesTestUser::withTrashed()->find(1);

        $this->assertTrue($taylor->trashed());

        $taylor->restore();

        $users = SoftDeletesTestUser::all();

        $this->assertCount(2, $users);
        $this->assertNull($users->find(1)->deleted_at);
        $this->assertNull($users->find(2)->deleted_at);
    }

    public function testOnlyTrashedOnlyReturnsTrashedRecords()
    {
        $this->createUsers();

        $users = SoftDeletesTestUser::onlyTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals(1, $users->first()->id);
    }

    public function testFirstOrNewIgnoresSoftDelete()
    {
        $this->createUsers();

        $taylor = SoftDeletesTestUser::firstOrNew(['id' => 1]);
        $this->assertEquals('taylorotwell@gmail.com', $taylor->email);
    }

    public function testWhereHasWithDeletedRelationship()
    {
        $this->createUsers();

        $abigail = SoftDeletesTestUser::where('email', 'abigailotwell@gmail.com')->first();
        $post = $abigail->posts()->create(['title' => 'First Title']);
        $post->delete();

        $users = SoftDeletesTestUser::has('posts')->get();

        $this->assertEquals(0, count($users));
    }

    /**
     * Helpers...
     */
    protected function createUsers()
    {
        $taylor = SoftDeletesTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $abigail = SoftDeletesTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $taylor->delete();
    }

    /**
     * Get a database connection instance.
     *
     * @return Connection
     */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestUser extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(SoftDeletesTestPost::class, 'user_id');
    }
}

/**
 * Eloquent Models...
 */
class SoftDeletesTestPost extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $table = 'posts';
    protected $guarded = [];
}
