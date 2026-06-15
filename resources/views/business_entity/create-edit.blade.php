@extends('userinterface::layouts.app')

@section('content')
    @php
        $isCreating = $isCreating ?? true;

        $entityOptions = $entities
            ->map(function ($entity) {
                $fields = is_string($entity->fields) ? json_decode($entity->fields, true) : $entity->fields ?? [];

                $metaFields = is_string($entity->meta_fields)
                    ? json_decode($entity->meta_fields, true)
                    : $entity->meta_fields ?? [];

                $isPivot = collect($entity->metas ?? [])->contains(function ($meta) {
                    return $meta->meta_key === 'is_pivot' && strtolower((string) $meta->meta_value) === 'true';
                });

                return [
                    'uid' => $entity->uid,
                    'entity_name' => $entity->entity_name,
                    'slug' => $entity->slug,
                    'fields' => array_values($fields ?: []),
                    'meta_fields' => array_values($metaFields ?: []),
                    'is_pivot' => $isPivot,
                ];
            })
            ->values();

        $fieldMapping = $businessEntity?->field_mapping ?? [];
        if (is_string($fieldMapping)) {
            $fieldMapping = json_decode($fieldMapping, true) ?: [];
        }
    @endphp

    <form id="businessEntityForm" method="POST"
        action="{{ $isCreating ? route('business-entities.store') : route('business-entities.update', $businessEntity->uid) }}">
        @csrf
        @if (!$isCreating)
            @method('PUT')
        @endif

        <input type="hidden" id="field_mapping" name="field_mapping" value="{{ old('field_mapping') }}">

        <div class="mb-3">
            <div class="sticky-top business-entity-sticky-top bg-body pb-2">
                <div class="mb-3">
                    <h5 class="mb-1 fs-6">
                        {{ $isCreating ? 'Create New Business Entity' : 'Edit Business Entity' }}
                    </h5>
                    <p class="mb-0 text-muted">
                        UID will be auto-generated
                    </p>
                </div>

                <div class="mb-3">
                    <div class="row px-2">
                        <div class="col-md-4">
                            <label for="business_entity_name" class="form-label">
                                Business Entity Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('business_entity_name') is-invalid @enderror"
                                id="business_entity_name" name="business_entity_name"
                                value="{{ old('business_entity_name', $businessEntity->business_entity_name ?? '') }}"
                                required>
                            @error('business_entity_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="ref_module" class="form-label">
                                Module <span class="text-danger">*</span>
                                <small>If no module is available, please create one.</small>
                            </label>

                            <select class="form-select @error('ref_module') is-invalid @enderror" id="ref_module"
                                name="ref_module" required>
                                <option value="">- Select Module -</option>

                                @foreach ($modules as $module)
                                    <option value="{{ $module->id }}"
                                        {{ old('ref_module', $businessEntity->ref_module ?? null) == $module->id ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('-', ' ', $module->name)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ref_module')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="desc" class="form-label">Description</label>
                            <textarea class="form-control @error('desc') is-invalid @enderror" id="desc" name="desc" rows="3">{{ old('desc', $businessEntity->desc ?? '') }}</textarea>
                            @error('desc')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mb-2">
                    Select entities, choose primary and meta fields, then save. Only selected fields are stored in
                    <code>field_mapping</code>.
                </div>
            </div>

            <div class="accordion" id="businessEntityAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="selectedEntitiesHeader">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#selectedEntities">
                            Selected Entities / Tables
                        </button>
                    </h2>
                    <div id="selectedEntities" class="accordion-collapse collapse show"
                        data-bs-parent="#businessEntityAccordion">
                        <div class="accordion-body">
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <button type="button" id="primaryAddBusinessEntityItem"
                                    class="btn btn-sm btn-outline-primary add-business-entity-item">
                                    <i class="fa-regular fa-fw fa-plus"></i>
                                    <span class="ms-1">Add Entity</span>
                                </button>

                                <button type="button" id="primaryAddAssociationItem"
                                    class="btn btn-sm btn-outline-secondary add-association-item">
                                    <i class="fa-solid fa-fw fa-link"></i>
                                    <span class="ms-1">Add Association</span>
                                </button>
                            </div>

                            <div id="selectedEntityPanels"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end mb-3 gap-2">
            <a href="{{ route('business-entities.index') }}" class="btn btn-sm btn-outline-dark">Cancel</a>
            <button type="submit" class="btn btn-sm btn-outline-primary">
                {{ $isCreating ? 'Create' : 'Update' }}
            </button>
        </div>
    </form>
@endsection

@push('styles')
    <style>
        .business-entity-sticky-top {
            z-index: 900;
        }

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

        .business-relationship-row {
            justify-content: flex-start;
            margin-left: 0;
        }

        .tooltip-inner {
            max-width: 350px;
        }
    </style>
@endpush

@push('scripts')
    <script>
        let availableEntities = @json($entityOptions);
        console.log(availableEntities);
        const existingMapping = @json(old('field_mapping') ? json_decode(old('field_mapping'), true) : $fieldMapping);
        const relationshipTypes = [
            'belongs_to',
            'has_one',
            'has_many',
            'belongs_to_many',
            'many_to_many',
            'has_many_through',
            'morph_one',
            'morph_many',
            'morph_to',
            'morph_to_many',
            'morphed_by_many',
            'one_to_one',
            'one_to_many'
        ];

        function findEntity(entityUid) {
            return availableEntities.find(entity => String(entity.uid) === String(entityUid));
        }

        function getFieldName(field) {
            return field.name || field.field || field.meta_key || '';
        }

        function getFieldLabel(field) {
            return field.label || getFieldName(field);
        }

        function getMetaFieldKey(field) {
            return field.meta_key || field.field || field.name || '';
        }

        function getStoredFields(entityMapping) {
            return entityMapping.fields || entityMapping.selected_fields || [];
        }

        function getStoredMetaFields(entityMapping) {
            return entityMapping.meta_fields || entityMapping.selected_meta_fields || [];
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

        function normalizeStoredEntity(entityMapping, index) {
            const sourceEntity = availableEntities.find(entity => {
                return String(entity.uid) === String(entityMapping.entity_uid) ||
                    String(entity.entity_name) === String(entityMapping.entity);
            });

            const entityName = entityMapping.entity || sourceEntity?.entity_name || '';

            return {
                entity_uid: entityMapping.entity_uid || sourceEntity?.uid || '',
                entity: entityName,
                slug: entityMapping.slug || sourceEntity?.slug || null,
                is_primary: Boolean(entityMapping.is_primary),
                is_association: Boolean(entityMapping.is_association),
                sort_order: entityMapping.sort_order || index + 1,
                fields: (
                    entityMapping.is_association ?
                    (sourceEntity?.fields || []) :
                    getStoredFields(entityMapping)
                ).map(field => ({
                    field: getFieldName(field),
                    label: getFieldLabel(field),
                    type: field.type || '',
                    input_type: field.input_type || null,
                    required: Boolean(field.required),
                    nullable: Boolean(field.nullable),
                    default: field.default ?? null
                })),
                meta_fields: getStoredMetaFields(entityMapping).map(field => ({
                    meta_key: getMetaFieldKey(field),
                    label: getFieldLabel(field),
                    type: field.type || '',
                    input_type: field.input_type || null,
                    required: Boolean(field.required),
                    nullable: Boolean(field.nullable),
                    display: field.display ?? true
                }))
            };
        }

        let selectedEntities = Array.isArray(existingMapping.entities) ?
            existingMapping.entities.map(normalizeStoredEntity) : [];
        let relationships = Array.isArray(existingMapping.relationships) ?
            existingMapping.relationships.map(normalizeRelationship) : [];
        let openEntityUid = selectedEntities[selectedEntities.length - 1]?.entity_uid || null;
        let submitConfirmedByPreview = false;

        function buildMapping() {

            let generatedRelationships = [...relationships];

            selectedEntities.forEach((entity, index) => {

                const association = selectedEntities[index + 1];
                const target = selectedEntities[index + 2];

                if (
                    !entity.is_association &&
                    association?.is_association &&
                    target &&
                    !target.is_association
                ) {

                    const associationFields =
                        (association.fields || [])
                        .map(field => getFieldName(field));

                    const sourceKey =
                        associationFields.find(field =>
                            field === `${entity.entity.replace(/s$/, '')}_id`
                        ) ||
                        associationFields.find(field =>
                            field === 'model_id'
                        );

                    const targetKey =
                        associationFields.find(field =>
                            field === `${target.entity.replace(/s$/, '')}_id`
                        );

                    if (sourceKey && targetKey) {
                        generatedRelationships = generatedRelationships.filter(
                            relationship => !(
                                relationship.source_entity === entity.entity &&
                                relationship.target_entity === target.entity
                            )
                        );
                        generatedRelationships.push({
                            type: 'belongs_to_many',

                            source_entity: entity.entity,
                            target_entity: target.entity,

                            through: association.entity,

                            source_key: 'id',
                            through_source_key: sourceKey,
                            through_target_key: targetKey,
                            target_key: 'id'
                        });
                    }
                }
            });

            return {
                primary_entity: selectedEntities.find(entity => entity.is_primary)?.entity || null,

                entities: selectedEntities.map((entity, index) => {

                    const mapping = {
                        entity_uid: entity.entity_uid,
                        entity: entity.entity,
                        slug: entity.slug,
                        is_primary: entity.is_primary,
                        is_association: entity.is_association === true,
                        sort_order: index + 1,
                        fields: entity.fields
                    };

                    if (!entity.is_association) {
                        mapping.meta_fields = entity.meta_fields;
                    }

                    return mapping;
                }),

                relationships: generatedRelationships
            };
        }

        function syncFieldMappingInput() {
            document.getElementById('field_mapping').value = JSON.stringify(buildMapping());
        }

        function fieldIsSelected(selectedEntity, fieldName) {
            return (selectedEntity.fields || []).some(field => getFieldName(field) === fieldName);
        }

        function metaFieldIsSelected(selectedEntity, metaKey) {
            return (selectedEntity.meta_fields || []).some(field => getMetaFieldKey(field) === metaKey);
        }

        function formatPrimaryField(field) {
            return {
                field: getFieldName(field),
                label: getFieldLabel(field),
                type: field.type || '',
                input_type: field.input_type || null,
                required: Boolean(field.required),
                nullable: Boolean(field.nullable),
                default: field.default ?? null
            };
        }

        function formatMetaField(field) {
            return {
                meta_key: getMetaFieldKey(field),
                label: getFieldLabel(field),
                type: field.type || '',
                input_type: field.input_type || null,
                required: Boolean(field.required),
                nullable: Boolean(field.nullable),
                display: field.display ?? true
            };
        }

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

            return metaFieldName.startsWith('m_') ? metaFieldName : `m_${metaFieldName}`;
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

        function findRelationshipsBetween(sourceEntity, targetEntity, mappingRelationships) {
            return mappingRelationships.filter(relationship => {
                return (
                    relationship.source_entity === sourceEntity.entity &&
                    relationship.target_entity === targetEntity.entity
                ) || (
                    relationship.source_entity === targetEntity.entity &&
                    relationship.target_entity === sourceEntity.entity
                );
            });
        }

        function renderRelationshipConnector(sourceEntity, targetEntity, mappingRelationships) {
            const matchingRelationships = findRelationshipsBetween(sourceEntity, targetEntity, mappingRelationships);

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

        function renderAssociationConnector(associationEntity) {

            return renderTreeNode(
                `Association: ${associationEntity.entity}`,
                '',
                renderFieldTree(
                    associationEntity.fields || [],
                    'No fields available'
                ),
                'fa-link'
            );
        }

        function renderBusinessEntityTree(mapping) {
            const mappingRelationships = mapping.relationships || [];
            const entityNodes = (mapping.entities || []).map((entity, index) => {
                if (entity.is_association) {
                    return '';
                }
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

                if (nextEntity.is_association) {
                    return entityNode +
                        renderAssociationConnector(nextEntity);
                }

                return entityNode + renderTreeNode(
                    'Relationship',
                    '',
                    renderRelationshipConnector(
                        entity,
                        nextEntity,
                        mappingRelationships
                    ),
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

        function showBusinessEntityPreviewModal(options = {}) {
            syncFieldMappingInput();
            const mapping = buildMapping();
            const modalBody = document.createElement('div');
            modalBody.innerHTML = renderBusinessEntityTree(mapping);
            const actions = [];

            if (typeof options.onConfirm === 'function') {
                actions.push({
                    id: 'confirmBusinessEntitySave',
                    label: options.confirmLabel || 'Continue',
                    className: 'btn btn-primary',
                    action: options.onConfirm
                });
            }

            showModal({
                header: {
                    enabled: true,
                    content: `
                <h5 class="modal-title">${escapeHtml(options.title || 'Business Entity Mapping')}</h5>
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
                    actions: actions,
                },
            });
        }

        function getEntityKeyOptions(entityUid) {
            const entity = findEntity(entityUid);
            const primaryKeys = (entity?.fields || []).map(getFieldName).filter(Boolean);
            const metaKeys = (entity?.meta_fields || []).map(getMetaFieldKey).filter(Boolean);

            return [...new Set([...primaryKeys, ...metaKeys])];
        }

        function getUnselectedEntities() {
            const selectedUids = selectedEntities.map(entity => String(entity.entity_uid));

            return availableEntities.filter(entity => !selectedUids.includes(String(entity.uid)));
        }

        function getUnselectedNormalEntities() {
            const selectedUids = selectedEntities.map(entity => String(entity.entity_uid));

            return availableEntities.filter(entity =>
                !entity.is_pivot &&
                !selectedUids.includes(String(entity.uid))
            );
        }

        function getUnselectedAssociationEntities() {
            const selectedUids = selectedEntities.map(entity => String(entity.entity_uid));

            return availableEntities.filter(entity =>
                entity.is_pivot &&
                !selectedUids.includes(String(entity.uid))
            );
        }

        function findLastNonAssociationIndex() {
            for (let i = selectedEntities.length - 1; i >= 0; i--) {
                if (!selectedEntities[i].is_association) {
                    return i;
                }
            }

            return -1;
        }

        function getRelationshipBetween(sourceEntity, targetEntity) {
            return relationships.find(relationship => {
                return relationship.source_entity === sourceEntity.entity &&
                    relationship.target_entity === targetEntity.entity;
            });
        }

        function removeRelationshipsForEntity(entityName) {
            relationships = relationships.filter(relationship => {
                return relationship.source_entity !== entityName &&
                    relationship.target_entity !== entityName;
            });
        }

        function renderRelationshipButton(sourceEntity, targetEntity, sourceIndex) {
            const relationship = getRelationshipBetween(sourceEntity, targetEntity);
            const label = relationship ?
                `${escapeHtml(relationship.type)}: ${escapeHtml(relationship.source_key || 'source')} -> ${escapeHtml(relationship.target_key || 'target')}` :
                'Relationship';

            return `
        <div class="d-flex align-items-center gap-2 my-2 business-relationship-row">
            <button type="button"
                class="btn btn-link p-0 text-decoration-none edit-business-relationship"
                data-source-index="${sourceIndex}"
                data-target-index="${sourceIndex + 1}">
                <i class="fa-solid fa-fw fa-link"></i>
                <span class="ms-1">${label}</span>
            </button>
        </div>
    `;
        }

        function renderFieldRows(selectedEntity, sourceEntity, entityIndex) {
            const fields = sourceEntity?.fields || [];

            if (!fields.length) {
                return '<tr><td colspan="7" class="text-center text-muted">No primary fields available.</td></tr>';
            }

            return fields.map(field => {
                const fieldName = getFieldName(field);

                return `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input business-primary-field-check"
                        data-entity-index="${entityIndex}"
                        data-field-name="${escapeHtml(fieldName)}"
                        ${fieldIsSelected(selectedEntity, fieldName) ? 'checked' : ''}>
                </td>
                <td>${escapeHtml(fieldName)}</td>
                <td>${escapeHtml(field.type || '')}</td>
                <td>${escapeHtml(getFieldLabel(field))}</td>
                <td>${field.required ? 'Yes' : 'No'}</td>
                <td>${field.nullable ? 'Yes' : 'No'}</td>
                <td>${escapeHtml(field.input_type || '')}</td>
            </tr>
        `;
            }).join('');
        }

        function renderAssociationFieldRows(sourceEntity) {
            const fields = sourceEntity?.fields || [];

            if (!fields.length) {
                return '<tr><td colspan="6" class="text-center text-muted">No fields available.</td></tr>';
            }

            return fields.map(field => `
                <tr>
                    <td>${escapeHtml(getFieldName(field))}</td>
                    <td>${escapeHtml(field.type || '')}</td>
                    <td>${escapeHtml(getFieldLabel(field))}</td>
                    <td>${field.required ? 'Yes' : 'No'}</td>
                    <td>${field.nullable ? 'Yes' : 'No'}</td>
                    <td>${escapeHtml(field.input_type || '')}</td>
                </tr>
            `).join('');
        }

        function renderMetaFieldRows(selectedEntity, sourceEntity, entityIndex) {
            const metaFields = sourceEntity?.meta_fields || [];

            if (!metaFields.length) {
                return '<tr><td colspan="7" class="text-center text-muted">No meta fields available.</td></tr>';
            }

            return metaFields.map(field => {
                const metaKey = getMetaFieldKey(field);

                return `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input business-meta-field-check"
                        data-entity-index="${entityIndex}"
                        data-meta-key="${escapeHtml(metaKey)}"
                        ${metaFieldIsSelected(selectedEntity, metaKey) ? 'checked' : ''}>
                </td>
                <td>${escapeHtml(metaKey)}</td>
                <td>${escapeHtml(field.type || '')}</td>
                <td>${escapeHtml(getFieldLabel(field))}</td>
                <td>${field.required ? 'Yes' : 'No'}</td>
                <td>${field.nullable ? 'Yes' : 'No'}</td>
                <td>${escapeHtml(field.input_type || '')}</td>
            </tr>
        `;
            }).join('');
        }

        function renderSelectedEntities() {
            const wrapper = document.getElementById('selectedEntityPanels');
            const primaryAddButton = document.getElementById('primaryAddBusinessEntityItem');
            const hasUnselectedEntities =
                getUnselectedNormalEntities().length > 0;

            const hasUnselectedAssociations =
                getUnselectedAssociationEntities().length > 0;

            if (primaryAddButton) {
                primaryAddButton.disabled = !hasUnselectedEntities;
            }

            const associationButton =
                document.getElementById('primaryAddAssociationItem');

            if (associationButton) {
                associationButton.disabled = !hasUnselectedAssociations;
            }

            wrapper.innerHTML = '';

            if (!selectedEntities.length) {
                wrapper.innerHTML = '<div class="text-center text-muted border rounded p-3">No entities added.</div>';
                syncFieldMappingInput();
                return;
            }

            wrapper.insertAdjacentHTML('beforeend', '<div class="accordion" id="businessEntityItemsAccordion"></div>');
            const accordion = document.getElementById('businessEntityItemsAccordion');

            selectedEntities.forEach((selectedEntity, entityIndex) => {
                const sourceEntity = findEntity(selectedEntity.entity_uid);
                const selectedCount = (selectedEntity.fields || []).length + (selectedEntity.meta_fields || [])
                    .length;
                const selectedCountLabel = selectedEntity.is_association ?
                    '' :
                    `<span class="text-muted ms-2">${selectedCount} selected fields</span>`;
                const collapseId = `businessEntityItem${entityIndex}`;
                const headerId = `businessEntityItemHeader${entityIndex}`;
                const isOpen = openEntityUid ?
                    selectedEntity.entity_uid === openEntityUid :
                    entityIndex === selectedEntities.length - 1;
                const isAssociation = selectedEntity.is_association === true;

                accordion.insertAdjacentHTML('beforeend', `
            <div class="accordion-item mb-2 border rounded">
                <h2 class="accordion-header" id="${headerId}">
                    <div class="d-flex align-items-center bg-light">
                        <button
                            class="accordion-button ${isOpen ? '' : 'collapsed'} bg-light py-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#${collapseId}"
                            aria-expanded="${isOpen ? 'true' : 'false'}"
                            aria-controls="${collapseId}">
                            <strong>${escapeHtml(selectedEntity.entity)}</strong>
                            ${selectedCountLabel}

                            ${selectedEntity.is_primary
                                ? '<span class="badge bg-primary ms-2">Primary</span>'
                                : ''
                            }

                            ${selectedEntity.is_association
                                ? '<span class="badge bg-secondary ms-2">Association</span>'
                                : ''
                            }
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger me-3 remove-business-entity-item" data-entity-index="${entityIndex}">
                            <i class="fas fa-fw fa-trash"></i>
                        </button>
                    </div>
                </h2>

                <div id="${collapseId}" class="accordion-collapse collapse ${isOpen ? 'show' : ''}" aria-labelledby="${headerId}">
                    <div class="accordion-body p-3">

                    ${isAssociation ? `
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Field</th>
                                                <th>Type</th>
                                                <th>Label</th>
                                                <th>Required</th>
                                                <th>Nullable</th>
                                                <th>Input</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${renderAssociationFieldRows(sourceEntity)}
                                        </tbody>
                                    </table>
                                </div>
                            ` : `

                                        <h6 class="text-primary">Primary Fields</h6>
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 70px;">#</th>
                                                        <th>Field</th>
                                                        <th>Type</th>
                                                        <th>Label</th>
                                                        <th>Required</th>
                                                        <th>Nullable</th>
                                                        <th>Input</th>
                                                    </tr>
                                                </thead>
                                                    <tbody>
                                                        ${renderFieldRows(selectedEntity, sourceEntity, entityIndex)}
                                                    </tbody>
                                                </table>
                                            </div>

                                            <h6 class="text-success">Meta Fields</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 70px;">#</th>
                                                            <th>Meta Key</th>
                                                            <th>Type</th>
                                                            <th>Label</th>
                                                            <th>Required</th>
                                                            <th>Nullable</th>
                                                            <th>Input</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${renderMetaFieldRows(selectedEntity, sourceEntity, entityIndex)}
                                                    </tbody>
                                                </table>
                                            </div>

                                        `}

                    </div>
                </div>
            </div>
        `);

                const nextEntity = selectedEntities[entityIndex + 1];

                if (
                    nextEntity &&
                    !selectedEntity.is_association &&
                    !nextEntity.is_association
                ) {
                    accordion.insertAdjacentHTML(
                        'beforeend',
                        renderRelationshipButton(
                            selectedEntity,
                            nextEntity,
                            entityIndex
                        )
                    );
                }
            });

            syncFieldMappingInput();
        }

        function showAddEntityModal() {
            const modalBody = document.createElement('div');
            modalBody.innerHTML = `
        <div class="row g-3">
            <div class="col-12">
                <label for="modalEntitySelector" class="form-label">Entity / Table</label>
                <select id="modalEntitySelector" class="form-select">
                    <option value="">- Select Entity -</option>

                    ${getUnselectedNormalEntities().map(entity => `
                                                            <option value="${escapeHtml(entity.uid)}">
                                                                ${escapeHtml(entity.entity_name)}
                                                            </option>
                                                    `).join('')}
                </select>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input"
                        type="checkbox"
                        id="modalEntityPrimary"
                        ${selectedEntities.length === 0 ? 'checked disabled' : 'disabled'}
                        title="The Primary Entity serves as the root entity for this Business Entity. The first entity added is automatically set as Primary to ensure a clear and consistent hierarchy.">
                    <label class="form-check-label" for="modalEntityPrimary">
                        Primary

                        ${selectedEntities.length > 0 ? `
                                        <i class="fa-solid fa-circle-info text-muted ms-1"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="right"
                                        data-bs-html="true"
                                        title="<strong>Primary Entity</strong><br>The first entity added becomes the Primary Entity. It acts as the root of the Business Entity hierarchy and cannot be changed later.">
                                        </i>
                                    ` : ''}
                    </label>
                </div>
            </div>
        </div>
    `;

            showModal({
                header: {
                    enabled: true,
                    content: `
                <h5 class="modal-title">Add Entity</h5>
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
                    actions: [{
                        id: 'confirmBusinessEntityItem',
                        label: 'Add Entity',
                        className: 'btn btn-primary',
                        action: function() {
                            addSelectedEntityFromModal();
                        }
                    }],
                },
            });

            setTimeout(() => {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                    bootstrap.Tooltip.getOrCreateInstance(el);
                });
            }, 100);
        }

        function showAddAssociationModal() {
            const modalBody = document.createElement('div');

            modalBody.innerHTML = `
        <div class="row g-3">
            <div class="col-12">
                <label for="modalAssociationSelector" class="form-label">Association Table</label>
                <select id="modalAssociationSelector" class="form-select">
                    <option value="">- Select Association -</option>

                    ${getUnselectedAssociationEntities().map(entity => `
                                                        <option value="${escapeHtml(entity.uid)}">
                                                            ${escapeHtml(entity.entity_name)}
                                                        </option>
                                                    `).join('')}
                </select>
            </div>
        </div>
    `;

            showModal({
                header: {
                    enabled: true,
                    content: `
                <h5 class="modal-title">Add Association</h5>
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
                    actions: [{
                        id: 'confirmAssociationItem',
                        label: 'Add Association',
                        className: 'btn btn-primary',
                        action: function() {
                            addSelectedAssociationFromModal();
                        }
                    }],
                },
            });
        }

        function addSelectedAssociationFromModal() {
            const selector = document.getElementById('modalAssociationSelector');
            const entity = findEntity(selector?.value);

            if (!entity) {
                return;
            }

            if (selectedEntities.some(selectedEntity =>
                    String(selectedEntity.entity_uid) === String(entity.uid)
                )) {
                return;
            }

            const associationEntity = {
                entity_uid: entity.uid,
                entity: entity.entity_name,
                slug: entity.slug,
                is_primary: false,
                is_association: true,
                sort_order: selectedEntities.length + 1,

                fields: (entity.fields || []).map(field => ({
                    field: getFieldName(field),
                    label: getFieldLabel(field),
                    type: field.type || '',
                    input_type: field.input_type || null,
                    required: Boolean(field.required),
                    nullable: Boolean(field.nullable),
                    default: field.default ?? null
                })),

                meta_fields: []
            };

            const insertIndex = findLastNonAssociationIndex();

            if (insertIndex === -1) {
                selectedEntities.push(associationEntity);
            } else {
                selectedEntities.splice(insertIndex + 1, 0, associationEntity);
            }

            selectedEntities = selectedEntities.map((selectedEntity, index) => ({
                ...selectedEntity,
                sort_order: index + 1
            }));

            openEntityUid = entity.uid;

            bootstrap.Modal.getInstance(
                document.getElementById('labModal')
            )?.hide();

            renderSelectedEntities();
        }

        function addSelectedEntityFromModal() {
            const selector = document.getElementById('modalEntitySelector');
            const primaryInput = document.getElementById('modalEntityPrimary');
            const entity = findEntity(selector?.value);

            if (!entity) {
                return;
            }

            if (selectedEntities.some(selectedEntity => String(selectedEntity.entity_uid) === String(entity.uid))) {
                return;
            }

            const isPrimary = selectedEntities.length === 0;

            const newSelectedEntity = {
                entity_uid: entity.uid,
                entity: entity.entity_name,
                slug: entity.slug,
                is_primary: isPrimary,
                sort_order: selectedEntities.length + 1,
                fields: [],
                meta_fields: []
            };

            selectedEntities.push(newSelectedEntity);

            selectedEntities = selectedEntities.map((selectedEntity, index) => ({
                ...selectedEntity,
                sort_order: index + 1
            }));

            openEntityUid = entity.uid;

            bootstrap.Modal.getInstance(document.getElementById('labModal'))?.hide();
            renderSelectedEntities();
        }

        function showRelationshipModal(sourceIndex, targetIndex) {
            const sourceEntity = selectedEntities[sourceIndex];
            const targetEntity = selectedEntities[targetIndex];

            if (!sourceEntity || !targetEntity) {
                return;
            }

            const existingRelationship = getRelationshipBetween(sourceEntity, targetEntity) || {};
            const sourceKeyOptions = getEntityKeyOptions(sourceEntity.entity_uid);
            const targetKeyOptions = getEntityKeyOptions(targetEntity.entity_uid);
            const modalBody = document.createElement('div');

            modalBody.innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Source Entity</label>
                <input type="text" class="form-control" value="${escapeHtml(sourceEntity.entity)}" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Target Entity</label>
                <input type="text" class="form-control" value="${escapeHtml(targetEntity.entity)}" disabled>
            </div>
            <div class="col-md-4">
                <label for="relationshipType" class="form-label">Type</label>
                <select id="relationshipType" class="form-select">
                    <option value="">- Select Type -</option>
                    ${relationshipTypes.map(type => `
                                                                                                    <option value="${escapeHtml(type)}" ${existingRelationship.type === type ? 'selected' : ''}>
                                                                                                        ${escapeHtml(type.replace(/_/g, ' '))}
                                                                                                    </option>
                                                                                                `).join('')}
                </select>
            </div>
            <div class="col-md-4">
                <label for="relationshipSourceKey" class="form-label">Source Key</label>
                <select id="relationshipSourceKey" class="form-select">
                    <option value="">- Select Source Key -</option>
                    ${sourceKeyOptions.map(key => `
                                                                                                    <option value="${escapeHtml(key)}" ${existingRelationship.source_key === key ? 'selected' : ''}>
                                                                                                        ${escapeHtml(key)}
                                                                                                    </option>
                                                                                                `).join('')}
                </select>
            </div>
            <div class="col-md-4">
                <label for="relationshipTargetKey" class="form-label">Target Key</label>
                <select id="relationshipTargetKey" class="form-select">
                    <option value="">- Select Target Key -</option>
                    ${targetKeyOptions.map(key => `
                                                                                                    <option value="${escapeHtml(key)}" ${existingRelationship.target_key === key ? 'selected' : ''}>
                                                                                                        ${escapeHtml(key)}
                                                                                                    </option>
                                                                                                `).join('')}
                </select>
            </div>
        </div>
    `;

            const actions = [{
                id: 'saveBusinessRelationship',
                label: 'Save Relationship',
                className: 'btn btn-primary',
                action: function() {
                    saveRelationship(sourceIndex, targetIndex);
                }
            }];

            if (existingRelationship.type) {
                actions.unshift({
                    id: 'removeBusinessRelationship',
                    label: 'Remove',
                    className: 'btn btn-outline-danger',
                    action: function() {
                        removeRelationship(sourceIndex, targetIndex);
                    }
                });
            }

            showModal({
                header: {
                    enabled: true,
                    content: `
                <h5 class="modal-title">Relationship</h5>
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
                    actions: actions,
                },
            });
        }

        function saveRelationship(sourceIndex, targetIndex) {
            const sourceEntity = selectedEntities[sourceIndex];
            const targetEntity = selectedEntities[targetIndex];
            const type = document.getElementById('relationshipType')?.value || '';
            const sourceKey = document.getElementById('relationshipSourceKey')?.value || '';
            const targetKey = document.getElementById('relationshipTargetKey')?.value || '';

            if (!sourceEntity || !targetEntity || !type || !sourceKey || !targetKey) {
                alert('Please select relationship type, source key, and target key.');
                return;
            }

            relationships = relationships.filter(relationship => {
                return !(relationship.source_entity === sourceEntity.entity &&
                    relationship.target_entity === targetEntity.entity);
            });

            relationships.push({
                source_entity: sourceEntity.entity,
                target_entity: targetEntity.entity,
                type: type,
                source_key: sourceKey,
                target_key: targetKey
            });

            bootstrap.Modal.getInstance(document.getElementById('labModal'))?.hide();
            renderSelectedEntities();
        }

        function removeRelationship(sourceIndex, targetIndex) {
            const sourceEntity = selectedEntities[sourceIndex];
            const targetEntity = selectedEntities[targetIndex];

            relationships = relationships.filter(relationship => {
                return !(relationship.source_entity === sourceEntity.entity &&
                    relationship.target_entity === targetEntity.entity);
            });

            bootstrap.Modal.getInstance(document.getElementById('labModal'))?.hide();
            renderSelectedEntities();
        }

        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('business-primary-field-check')) {
                const entityIndex = Number(event.target.dataset.entityIndex);
                const fieldName = event.target.dataset.fieldName;
                const selectedEntity = selectedEntities[entityIndex];
                const sourceEntity = findEntity(selectedEntity.entity_uid);
                const sourceField = (sourceEntity?.fields || []).find(field => getFieldName(field) === fieldName);

                if (!sourceField) {
                    return;
                }

                selectedEntity.fields = selectedEntity.fields || [];

                if (event.target.checked && !fieldIsSelected(selectedEntity, fieldName)) {
                    selectedEntity.fields.push(formatPrimaryField(sourceField));
                }

                if (!event.target.checked) {
                    selectedEntity.fields = selectedEntity.fields.filter(field => getFieldName(field) !==
                        fieldName);
                }

                syncFieldMappingInput();
                openEntityUid = selectedEntity.entity_uid;
                renderSelectedEntities();
                return;
            }

            if (event.target.classList.contains('business-meta-field-check')) {
                const entityIndex = Number(event.target.dataset.entityIndex);
                const metaKey = event.target.dataset.metaKey;
                const selectedEntity = selectedEntities[entityIndex];
                const sourceEntity = findEntity(selectedEntity.entity_uid);
                const sourceField = (sourceEntity?.meta_fields || []).find(field => getMetaFieldKey(field) ===
                    metaKey);

                if (!sourceField) {
                    return;
                }

                selectedEntity.meta_fields = selectedEntity.meta_fields || [];

                if (event.target.checked && !metaFieldIsSelected(selectedEntity, metaKey)) {
                    selectedEntity.meta_fields.push(formatMetaField(sourceField));
                }

                if (!event.target.checked) {
                    selectedEntity.meta_fields = selectedEntity.meta_fields.filter(field => getMetaFieldKey(
                        field) !== metaKey);
                }

                syncFieldMappingInput();
                openEntityUid = selectedEntity.entity_uid;
                renderSelectedEntities();
            }
        });

        document.addEventListener('click', function(event) {
            const removeButton = event.target.closest('.remove-business-entity-item');
            if (!removeButton) {
                return;
            }

            const entityIndex = Number(removeButton.dataset.entityIndex);
            const removedEntityUid = selectedEntities[entityIndex]?.entity_uid;
            const removedEntityName = selectedEntities[entityIndex]?.entity;
            selectedEntities.splice(entityIndex, 1);
            removeRelationshipsForEntity(removedEntityName);
            selectedEntities = selectedEntities.map((selectedEntity, index) => ({
                ...selectedEntity,
                sort_order: index + 1
            }));

            if (selectedEntities.length && !selectedEntities.some(selectedEntity => selectedEntity.is_primary)) {
                selectedEntities[0].is_primary = true;
            }

            if (openEntityUid === removedEntityUid) {
                openEntityUid = selectedEntities[entityIndex]?.entity_uid ||
                    selectedEntities[entityIndex - 1]?.entity_uid ||
                    selectedEntities[0]?.entity_uid ||
                    null;
            }

            renderSelectedEntities();
        });

        document.addEventListener('click', function(event) {
            const addButton = event.target.closest('.add-business-entity-item');
            if (addButton) {
                showAddEntityModal();
                return;
            }

            const associationButton = event.target.closest('.add-association-item');
            if (associationButton) {
                showAddAssociationModal();
                return;
            }

            const relationshipButton = event.target.closest('.edit-business-relationship');
            if (!relationshipButton) {
                return;
            }

            showRelationshipModal(
                Number(relationshipButton.dataset.sourceIndex),
                Number(relationshipButton.dataset.targetIndex)
            );
        });

        document.addEventListener('shown.bs.collapse', function(event) {
            if (!event.target.id.startsWith('businessEntityItem')) {
                return;
            }

            const entityIndex = Number(event.target.id.replace('businessEntityItem', ''));
            openEntityUid = selectedEntities[entityIndex]?.entity_uid || openEntityUid;
        });

        document.addEventListener('hidden.bs.collapse', function(event) {
            if (!event.target.id.startsWith('businessEntityItem')) {
                return;
            }

            const accordion = document.getElementById('businessEntityItemsAccordion');

            const openPanels = accordion.querySelectorAll(
                '.accordion-collapse.show'
            );

            // If at least one is still open, do nothing
            if (openPanels.length > 0) {
                return;
            }

            // Find another panel to reopen
            const allPanels = accordion.querySelectorAll(
                '.accordion-collapse'
            );

            const panelToOpen = [...allPanels].find(
                panel => panel.id !== event.target.id
            );

            if (panelToOpen) {
                bootstrap.Collapse.getOrCreateInstance(panelToOpen).show();

                const entityIndex = Number(
                    panelToOpen.id.replace('businessEntityItem', '')
                );

                openEntityUid = selectedEntities[entityIndex]?.entity_uid || openEntityUid;
            }
        });
        document.getElementById('businessEntityForm').addEventListener('submit', function(event) {
            syncFieldMappingInput();

            if (!selectedEntities.length) {
                event.preventDefault();
                alert('Please add at least one entity.');
                return;
            }

            if (!submitConfirmedByPreview) {
                event.preventDefault();
                showBusinessEntityPreviewModal({
                    title: '{{ $isCreating ? 'Review Business Entity Before Create' : 'Review Business Entity Before Update' }}',
                    confirmLabel: '{{ $isCreating ? 'Create' : 'Update' }}',
                    onConfirm: function() {
                        submitConfirmedByPreview = true;
                        bootstrap.Modal.getInstance(document.getElementById('labModal'))?.hide();
                        document.getElementById('businessEntityForm').requestSubmit();
                    }
                });
            }
        });

        selectedEntities = selectedEntities.map(normalizeStoredEntity);
        renderSelectedEntities();
    </script>
@endpush
