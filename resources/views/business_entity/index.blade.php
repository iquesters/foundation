@extends('userinterface::layouts.app')

@section('content')
    @php
        $businessEntityMappings = $businessEntities->mapWithKeys(function ($businessEntity) {
            $fieldMapping = $businessEntity->field_mapping ?? [];

            if (is_string($fieldMapping)) {
                $fieldMapping = json_decode($fieldMapping, true) ?: [];
            }

            return [
                $businessEntity->uid => [
                    'name' => $businessEntity->business_entity_name,
                    'uid' => $businessEntity->uid,
                    'mapping' => $fieldMapping,
                ],
            ];
        });
    @endphp

    <div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="fs-6 text-muted">Total {{ $businessEntities->count() }} Business Entities</h5>
            <a href="{{ route('business-entities.create') }}" class="btn btn-sm btn-outline-primary">
                <i class="fa-regular fa-fw fa-plus"></i>
                <span class="d-none d-md-inline-block ms-1">Business Entity</span>
            </a>
        </div>

        <div class="table-responsive">
            <table id="business-entities-table" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($businessEntities as $businessEntity)
                        <tr>
                            <td>
                                <a href="{{ route('business-entities.edit', $businessEntity->uid) }}"
                                    class="text-decoration-none">
                                    {{ $businessEntity->business_entity_name }}
                                </a>
                                <br>
                                <small><small class="text-muted">{{ $businessEntity->uid }}</small></small>
                            </td>
                            <td>
                                <x-userinterface::status :status="$businessEntity->status" />
                            </td>
                            <td>{{ $businessEntity->created_at->format('d M Y') }}</td>
                            <td>
                                <div class="d-flex align-content-center justify-content-center gap-2">
                                    <button type="button"
                                        class="btn btn-sm btn-outline-secondary view-business-entity-details"
                                        data-business-entity-uid="{{ $businessEntity->uid }}">
                                        <i class="fa-solid fa-fw fa-diagram-project"></i>
                                    </button>

                                    <a class="btn btn-sm btn-outline-dark"
                                        href="{{ route('business-entities.edit', $businessEntity->uid) }}">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </a>

                                    <form action="{{ route('business-entities.destroy', $businessEntity->uid) }}"
                                        method="POST" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-fw fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .business-entity-tree,
        .business-entity-tree ul,
        .business-entity-tree li {
            list-style: none;
            margin: 0;
            padding-left: 0;
        }

        .business-entity-tree {
            min-width: max-content;
        }

        .business-entity-tree .tree-item {
            position: relative;
            margin-bottom: 4px;
        }

        .business-entity-tree .tree-item-content {
            padding: 4px 8px;
            border-radius: 4px;
        }

        .business-entity-tree .tree-item-children {
            margin-left: 25px;
            margin-top: 4px;
            position: relative;
        }

        .business-entity-tree .tree-item-children::before {
            content: "";
            position: absolute;
            left: 0;
            top: -4px;
            bottom: 20px;
            width: 1px;
            border-left: 1px solid #adb5bd;
        }

        .business-entity-tree .tree-item-children .tree-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: 18px;
            width: 20px;
            border-top: 1px solid #adb5bd;
        }

        .business-entity-tree .tree-item-children .tree-item-content {
            padding-left: 25px;
        }

        .business-entity-tree .tree-item-value {
            color: #6c757d;
            margin-left: 6px;
        }

        .business-entity-tree-header {
            padding: 4px 8px;
        }

        .business-entity-sequence {
            margin-left: 25px;
            position: relative;
        }

        .business-entity-sequence::before {
            content: "";
            position: absolute;
            left: 0;
            top: 18px;
            bottom: 20px;
            width: 1px;
            border-left: 1px solid #adb5bd;
        }

        .business-entity-sequence>.tree-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: 18px;
            width: 20px;
            border-top: 1px solid #adb5bd;
        }

        .business-entity-sequence>.tree-item>.tree-item-content {
            padding-left: 25px;
        }
    </style>
@endpush

@push('scripts')
    <script>
        const businessEntityMappings = @json($businessEntityMappings);

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getMappingFieldName(field) {
            return field.field || field.name || field.meta_key || '';
        }

        function getMappingMetaFieldName(field) {
            return field.meta_key || field.field || field.name || '';
        }

        function getDisplayMetaFieldName(field) {
            const metaFieldName = getMappingMetaFieldName(field);

            return metaFieldName.startsWith('m_') ?
                metaFieldName :
                `m_${metaFieldName}`;
        }


        function normalizeRelationship(relationship) {
            return {
                source_entity: relationship.source_entity || relationship.from_entity || '',
                target_entity: relationship.target_entity || relationship.to_entity || '',
                type: relationship.type || relationship.relation_type || '',
                source_key: relationship.source_key || relationship.from_field || '',
                target_key: relationship.target_key || relationship.to_field || ''
            };
        }

        function normalizeMapping(mapping = {}) {
            return {
                primary_entity: mapping.primary_entity || null,
                entities: Array.isArray(mapping.entities) ? mapping.entities : [],
                relationships: Array.isArray(mapping.relationships) ?
                    mapping.relationships.map(normalizeRelationship) : []
            };
        }

        function renderTreeNode(label, value = '', children = '', icon = 'fa-circle') {
            return `
            <li class="tree-item">
                <div class="tree-item-content">
                    <i class="fas fa-fw ${icon} text-muted me-1"></i>
                    <span class="tree-item-key">${escapeHtml(label)}</span>
                    ${value ? `<span class="tree-item-value">- ${escapeHtml(value)}</span>` : ''}
                </div>
                ${children ? `<ul class="tree-item-children">${children}</ul>` : ''}
            </li>
        `;
        }

        function renderFieldTree(fields, emptyLabel) {
            if (!fields.length) {
                return renderTreeNode(emptyLabel, '', '', 'fa-minus');
            }

            return fields.map(field => renderTreeNode(
                getMappingFieldName(field),
                [field.label, field.type].filter(Boolean).join(' | '),
                '',
                'fa-table-columns'
            )).join('');
        }

        function renderMetaFieldTree(fields) {
            return fields.map(field => renderTreeNode(
                getDisplayMetaFieldName(field),
                [field.label, field.type].filter(Boolean).join(' | '),
                '',
                'fa-tags'
            )).join('');
        }

        function findRelationshipsBetween(sourceEntity, targetEntity, relationships) {
            return relationships.filter(relationship => {
                return (
                    relationship.source_entity === sourceEntity.entity &&
                    relationship.target_entity === targetEntity.entity
                ) || (
                    relationship.source_entity === targetEntity.entity &&
                    relationship.target_entity === sourceEntity.entity
                );
            });
        }

        function renderRelationshipConnector(sourceEntity, targetEntity, relationships) {
            const matchingRelationships = findRelationshipsBetween(sourceEntity, targetEntity, relationships);

            if (!matchingRelationships.length) {
                return renderTreeNode(
                    `${sourceEntity.entity || 'source'} -> ${targetEntity.entity || 'target'}`,
                    'No relationship configured',
                    '',
                    'fa-link'
                );
            }

            return matchingRelationships.map(relationship => {
                const relationLabel =
                    `${relationship.source_entity || 'source'} -> ${relationship.target_entity || 'target'}`;
                const relationValue = [
                    relationship.type,
                    relationship.source_key && relationship.target_key ?
                    `${relationship.source_key} -> ${relationship.target_key}` :
                    ''
                ].filter(Boolean).join(' | ');

                return renderTreeNode(relationLabel, relationValue, '', 'fa-link');
            }).join('');
        }

        function renderBusinessEntityTree(mapping) {
            const mappingRelationships = mapping.relationships || [];
            const entityNodes = (mapping.entities || []).map((entity, index) => {
                const entityFields = [
                    renderFieldTree(entity.fields || [], 'No fields selected'),
                    renderMetaFieldTree(entity.meta_fields || [])
                ].join('');
                const entityNode = renderTreeNode(
                    entity.entity || 'Unnamed entity',
                    entity.is_primary ? 'Primary' : '',
                    entityFields || renderTreeNode('No fields selected', '', '', 'fa-minus'),
                    entity.is_primary ? 'fa-star' : 'fa-table'
                );
                const nextEntity = (mapping.entities || [])[index + 1];

                if (!nextEntity) {
                    return entityNode;
                }

                return entityNode + renderTreeNode(
                    'Relationship',
                    '',
                    renderRelationshipConnector(entity, nextEntity, mappingRelationships),
                    'fa-diagram-project'
                );
            }).join('');

            return `
        <div class="overflow-auto bg-light border rounded p-2" style="max-height: 60vh;">
            <div class="business-entity-tree-header">
                <i class="fas fa-fw fa-sitemap text-muted me-1"></i>
                <span class="tree-item-key">Selected Entities / Tables</span>
                <span class="tree-item-value">- ${(mapping.entities || []).length} selected</span>
            </div>
            <ul class="business-entity-tree business-entity-sequence">
                ${entityNodes || renderTreeNode('No entities selected', '', '', 'fa-minus')}
            </ul>
        </div>
    `;
        }

        function showBusinessEntityDetails(uid) {
            const businessEntity = businessEntityMappings[uid];

            if (!businessEntity) {
                return;
            }

            const modalBody = document.createElement('div');
            modalBody.innerHTML = renderBusinessEntityTree(businessEntity.mapping || {});

            showModal({
                header: {
                    enabled: true,
                    content: `
                    <h5 class="modal-title">${escapeHtml(businessEntity.name)}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                `,
                },
                body: {
                    enabled: true,
                    content: modalBody,
                },
                footer: {
                    enabled: true,
                    allowCancel: true,
                    actions: [],
                },
            });
        }

        $(document).ready(function() {
            $('#business-entities-table').DataTable({
                responsive: true,
                order: [
                    [2, 'desc']
                ]
            });

            $(document).on('click', '.view-business-entity-details', function() {
                showBusinessEntityDetails($(this).data('business-entity-uid'));
            });
        });
    </script>
@endpush
