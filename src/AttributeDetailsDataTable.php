<?php

class AttributeDetailsDataTable extends AdminDataTable
{
    public function dataTable($query)
    {
        return datatables($query)
            ->addColumn('title', function ($attributeDetail) {
                return view('admin.datatables.columns.attribute-details-title', [
                    'title' => $attributeDetail->title,
                    'createdAt' => $attributeDetail->created_at,
                    'updatedAt' => $attributeDetail->updated_at
                ]);
            })
            ->addColumn('icon', function ($attributeDetail) {
                return view('admin.datatables.columns.attribute-icon', [
                    'icon' => $attributeDetail->getIcon(),
                    'type' => $attributeDetail->icon_type
                ]);
            })
            ->addColumn('action', function ($attributeDetail) {
                return view('admin.datatables.columns.actions', [
                    'entityId' => $attributeDetail->id,
                    'entityName' => AttributeDetail::getRouteName()
                ]);
            })
            ->addColumn('status', function ($entity) {
                return view('admin.datatables.columns.status', [
                    'status' => $entity->status,
                    'entityId' => $entity->id,
                    'entityClass' => get_class($entity)
                ]);
            })
            ->addColumn('created_by', function ($attributeDetail) {
                return view('admin.datatables.columns.profile', [
                    'user' => $attributeDetail->createdByUser
                ]);
            })
            ->addColumn('updated_by', function ($attributeDetail) {
                return view('admin.datatables.columns.profile', [
                    'user' => $attributeDetail->updatedByUser
                ]);
            })
            ->rawColumns(['action', 'status', 'created_by', 'updated_by', 'title', 'icon'])
            ->orderColumn('created_at', 'attribute_details.created_at $1')
            ->orderColumn('updated_at', 'attribute_details.updated_at $1')
            ->orderColumn('status', 'attribute_details.status $1')
            ->orderColumn('created_by', 'createdByUser.username $1')
            ->orderColumn('updated_by', 'updatedByUser.username $1');
    }

    public function query(AttributeDetail $model)
    {
        $attributesTable = Attribute::getTableName();
        $attributeDetailsTable = AttributeDetail::getTableName();
        return $model->newQuery()->with(['attribute', 'createdByUser', 'updatedByUser'])
            ->join($attributesTable, $attributeDetailsTable . '.attribute_id', '=', $attributesTable . '.id')
            ->where('attributes.post_type', $this->postType)
            ->select(
                $attributesTable . '.title as attribute_title',
                $attributeDetailsTable . '.title',
                $attributeDetailsTable . '.icon',
                $attributeDetailsTable . '.icon_type',
                $attributeDetailsTable . '.id',
                $attributeDetailsTable . '.created_by',
                $attributeDetailsTable . '.created_at',
                $attributeDetailsTable . '.updated_at',
                $attributeDetailsTable . '.updated_by',
                $attributeDetailsTable . '.status',
                $attributeDetailsTable . '.position'
            );
    }

    /**
     * @inheritdoc
     */
    protected function getBuilderParameters()
    {
        $params = parent::getBuilderParameters();
        $params['order'] = [
            [1, 'asc'],
        ];

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function getColumns()
    {
        $attributeDetailsTable = AttributeDetail::getTableName();
        return [
            [
                'name' => $attributeDetailsTable . '.position',
                'data' => 'position',
                'title' => __('admin.#'),
                'searchable' => false
            ],
            [
                'name' => $attributeDetailsTable . '.title',
                'data' => 'title',
                'title' => __('admin.title'),
            ],
            [
                'name' => $attributeDetailsTable . '.icon',
                'data' => 'icon',
                'title' => __('admin.icon'),
                'orderable' => false
            ],
            [
                'name' => $attributeDetailsTable . '.title',
                'data' => 'attribute_title',
                'title' => __('admin.attribute'),
            ],
            [
                'name' => 'attribute_details.status',
                'data' => 'status',
                'title' => __('admin.status'),
                'inputType' => 'select',
                'acceptable' => [
                    Status::ACTIVE => __('admin.active'),
                    Status::INACTIVE => __('admin.inactive'),
                ]
            ],
            [
                'name' => 'createdByUser.username',
                'data' => 'created_by',
                'title' => __('admin.created_by'),
                'searchable' => true
            ],
            [
                'name' => 'updatedByUser.username',
                'data' => 'updated_by',
                'title' => __('admin.updated_by'),
                'searchable' => false
            ],
        ];
    }
}
