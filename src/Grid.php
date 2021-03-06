<?php
namespace Encore\Admin;

use Closure;
use Encore\Admin\Grid\Action;
use Encore\Admin\Grid\Row;
use Encore\Admin\Grid\Model;
use Encore\Admin\Grid\Column;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Encore\Admin\Pagination\AdminThreePresenter;
use Illuminate\Database\Eloquent\Relations\Relation;

class Grid {

    /**
     * The grid data model instance.
     *
     * @var \Encore\Admin\Grid\Model
     */
    protected $model;

    /**
     * Collection of all grid columns.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $columns;

    /**
     * Collection of all data rows.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $rows;

    /**
     * Rows callable fucntion.
     *
     * @var \Closure
     */
    protected $rowsCallback;

    /**
     * All column names of the grid.
     *
     * @var array
     */
    public $columnNames = [];

    /**
     * Action instance of grid.
     *
     * @var \Encore\Admin\Grid\Action
     */
    protected $actions;

    /**
     * Grid builder.
     *
     * @var \Closure
     */
    protected $builder;

    /**
     * Mark if the grid is builded.
     *
     * @var bool
     */
    protected $builded = false;

    /**
     * All variables in grid view.
     *
     * @var array
     */
    protected $variables = [];

    /**
     * Title of the grid.
     *
     * @var string
     */
    protected $title = 'List';

    /**
     * The grid Filter.
     *
     * @var \Encore\Admin\Filter
     */
    protected $filter;

    /**
     * Create a new grid instance.
     *
     * @param Eloquent $model
     * @param callable $builder
     */
    public function __construct(Eloquent $model, Closure $builder)
    {
        $this->model    = new Model($model);
        $this->columns  = new Collection();
        $this->rows     = new Collection();
        $this->builder  = $builder;

        $this->setupFilter();
    }

    /**
     * Add column to Grid.
     *
     * @param string $name
     * @param string $label
     * @return Column
     */
    public function column($name, $label = '')
    {
        if(strpos($name, '.') !== false) {
            list($relationName, $relationColumn) = explode('.', $name);

            $relation = $this->model()->eloquent()->$relationName();

            $label = empty($label) ? ucfirst($relationColumn) : $label;
        }

        $column = $this->addColumn($name, $label);

        if(isset($relation) && $relation instanceof Relation) {
            $this->model()->with($relationName);
            $column->setRelation($relation, $relationColumn);
        }

        return $column;
    }

    /**
     * Batch add column to grid.
     *
     * @example
     * 1.$grid->columns(['name' => 'Name', 'email' => 'Email' ...]);
     * 2.$grid->columns('name', 'email' ...)
     *
     * @param array $columns
     * @return Collection|void
     */
    public function columns($columns = [])
    {
        if(func_num_args() == 0) {
            return $this->columns;
        }

        if(func_num_args() == 1 && is_array($columns)) {
            foreach($columns as $column => $label) {
                $this->column($column, $label);
            }

            return;
        }

        foreach(func_get_args() as $column) {
            $this->column($column);
        }
    }

    /**
     * Add column to grid.
     *
     * @param string $column
     * @param string $label
     * @return Column
     */
    protected function addColumn($column = '', $label = '')
    {
        //$label = $label ?: Str::upper($column);

        return $this->columns[] = new Column($column, $label);
    }

    /**
     * Get Grid model.
     *
     * @return Model
     */
    public function model()
    {
        return $this->model;
    }

    /**
     * Paginate the grid.
     *
     * @param int $perPage
     * @return void
     */
    public function paginate($perPage = null)
    {
        $this->model()->paginate($perPage);
    }

    /**
     * Get the grid paginator.
     *
     * @return mixed
     */
    public function paginator()
    {
        $query = Input::all();

        return $this->model()->eloquent()->appends($query)->render(
            new AdminThreePresenter($this->model()->eloquent())
        );
    }

    /**
     * @param string $actions
     * @return $this
     */
    public function actions($actions = 'show|edit|delete')
    {
        $this->actions = new Action($actions);

        return $this;
    }

    /**
     * Render grid actions for each data item.
     *
     * @param $id
     * @return mixed
     */
    public function renderActions($id)
    {
        return $this->actions->render($id);
    }

    /**
     * Build the grid.
     *
     * @return void
     */
    public function build()
    {
        if($this->builded) return;

        call_user_func($this->builder, $this);

        $data = $this->filter->execute();

        $this->columns->map(function($column) use (&$data) {
            $data = $column->map($data);

            $this->columnNames[] = $column->getName();
        });

        $this->buildRows($data);

        $this->buildActions();

        $this->builded = true;
    }

    /**
     * Build the grid rows.
     *
     * @param array $data
     * @return void
     */
    protected function buildRows(array $data)
    {
        $this->rows = collect($data)->map(function($val, $key){
            return new Row($key, $val);
        });

        if($this->rowsCallback) {
            $this->rows->map($this->rowsCallback);
        }
    }

    /**
     * Build grid action if grid action is null.
     *
     * @return void
     */
    protected function buildActions()
    {
        if(is_null($this->actions)) {
            $this->actions();
        }
    }

    /**
     * Set grid row callback function.
     *
     * @param callable $callable
     * @return Collection
     */
    public function rows(Closure $callable = null)
    {
        if(is_null($callable)) {
            return $this->rows;
        }

        $this->rowsCallback = $callable;
    }

    /**
     * Setup grid filter.
     *
     * @return void
     */
    protected function setupFilter()
    {
        $this->filter = new Filter($this->model());
    }

    /**
     * Set the grid filter.
     *
     * @param callable $callback
     */
    public function filter(Closure $callback)
    {
        call_user_func($callback, $this->filter);
    }

    /**
     * Render the grid filter
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function renderFilter()
    {
        return $this->filter->render();
    }

    /**
     * Set the grid title.
     *
     * @param string $title
     * @return string
     */
    public function title($title = '')
    {
        if(func_num_args() == 0) {
            return $this->title;
        }

        $this->title = $title;
    }

    /**
     * Get current resource uri.
     *
     * @return string
     */
    public function resource()
    {
        return app('router')->current()->getPath();
    }

    /**
     * Add variables to grid view.
     *
     * @param array $variables
     * @return $this
     */
    public function with($variables = [])
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * Get all variables will used in grid view.
     *
     * @return array
     */
    protected function variables()
    {
        $this->variables['grid'] = $this;

        return $this->variables;
    }

    /**
     * Get the string contents of the grid view.
     *
     * @return string
     */
    public function render()
    {
        $this->build();

        return view('admin::grid', $this->variables())->render();
    }

    /**
     * Dynamically add columns to the grid view.
     *
     * @param $method
     * @param $arguments
     * @return $this|Column
     */
    public function __call($method, $arguments)
    {
        if(Schema::hasColumn($this->model()->getTable(), $method))
        {
            $label = isset($arguments[0]) ? $arguments[0] : ucfirst($method);

            return $this->addColumn($method, $label);
        }

        $relation = $this->model()->eloquent()->$method();

        if($relation instanceof Relation) {

            $this->model()->with($method);

            return $this->addColumn()->setRelation($method);
        }
    }

    /**
     * Get the string contents of the grid view.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}