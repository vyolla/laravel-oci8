<?php

namespace yajra\Oci8\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

class OracleAutoIncrementHelper
{

    protected $connection;

    protected $trigger;

    protected $sequence;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->sequence = new Sequence($connection);
        $this->trigger = new Trigger($connection);
    }

    /**
     * create sequence and trigger for autoIncrement support
     *
     * @param  Blueprint $blueprint
     * @param  string $table
     * @return null
     */
    public function createAutoIncrementObjects(Blueprint $blueprint, $table)
    {
        $column = $this->getQualifiedAutoIncrementColumn($blueprint);

        // return if no qualified AI column
        if (is_null($column)) {
            return;
        }

        $col = $column->name;
        $start = isset($column->start) ? $column->start : 1;

        // get table prefix
        $prefix = $this->connection->getTablePrefix();

        // create sequence for auto increment
        $sequenceName = $this->createObjectName($prefix, $table, $col, 'SQ');
        $this->sequence->create($sequenceName, $start, $column->nocache);

        // create trigger for auto increment work around
        $triggerName = $this->createObjectName($prefix, $table, $col, 'TR');
        $this->trigger->autoIncrement($prefix . $table, $col, $triggerName, $sequenceName);
    }

    /**
     * get qualified autoincrement column
     *
     * @param  Blueprint $blueprint
     * @return Fluent|null
     */
    public function getQualifiedAutoIncrementColumn(Blueprint $blueprint)
    {
        $columns = $blueprint->getColumns();

        // search for primary key / autoIncrement column
        foreach ($columns as $column) {
            // if column is autoIncrement set the primary col name
            if ($column->autoIncrement) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Create an object name that limits to 30chars
     *
     * @param  string $prefix
     * @param  string $table
     * @param  string $col
     * @param  string $type
     * @return string
     */
    private function createObjectName($prefix, $table, $col, $type)
    {
        // max object name length is 30 chars
        //return substr($prefix . $table . '_' . $col . '_' . $type, 0, 30);
        return substr($prefix . $type . '_' .  $table . '_' . $col, 0, 30);
    }

    /**
     * drop sequence and triggers if exists, autoincrement objects
     *
     * @param  string $table
     * @return null
     */
    public function dropAutoIncrementObjects($table)
    {
        // drop sequence and trigger object
        $prefix = $this->connection->getTablePrefix();
        // get the actual primary column name from table
        $col = $this->getPrimaryKey($prefix . $table);
        // if primary key col is set, drop auto increment objects
        if (isset($col) and ! empty($col)) {
            // drop sequence for auto increment
            $sequenceName = $this->createObjectName($prefix, $table, $col, 'SQ');
            $this->sequence->drop($sequenceName);

            // drop trigger for auto increment work around
            $triggerName = $this->createObjectName($prefix, $table, $col, 'TR');
            $this->trigger->drop($triggerName);
        }
    }

    /**
     * get table's primary key
     *
     * @param  string $table
     * @return string
     */
    public function getPrimaryKey($table)
    {
        if ( ! $table) {
            return '';
        }

        $data = $this->connection->selectOne("
			SELECT cols.column_name
			FROM all_constraints cons, all_cons_columns cols
			WHERE cols.table_name = upper('{$table}')
				AND cons.constraint_type = 'P'
				AND cons.constraint_name = cols.constraint_name
				AND cons.owner = cols.owner
				AND cols.position = 1
				AND cons.owner = (select user from dual)
			ORDER BY cols.table_name, cols.position
			");

        if (count($data)) {
            return $data->column_name;
        }

        return '';
    }

    /**
     * @return Sequence
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param Sequence $sequence
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
    }

    /**
     * @return Trigger
     */
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * @param Trigger $trigger
     */
    public function setTrigger($trigger)
    {
        $this->trigger = $trigger;
    }

}
