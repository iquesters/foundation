/**
 * Business entity builder (foundation admin, business_entity/create-edit).
 *
 * Edits the definition kept in the hidden #definition input (see
 * BusinessEntityDefinition):
 *   { version: 2, parts: [ { alias, entity_uid | model, table, label?, link?, fields, meta_fields } ] }
 * The first part is the primary (root) entity; every other part links to an
 * earlier part with a relation type (has_one, has_many, belongs_to,
 * belongs_to_many, morph_*...), composed (edited inside the record) or as a
 * reference (points at existing records).
 *
 * - Entities are added from a picker in the UI's modal (modal.js); entities
 *   already used are disabled.
 * - "Review & save" asks the server to check the definition (every table,
 *   column and pivot it uses must exist; nothing is created) and shows the
 *   whole structure before saving.
 *
 * DOM is built with textContent only (no HTML from data).
 */
(function () {
    'use strict';

    const SEPARATOR = '__';

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('businessEntityBuilder');
        if (!root) {
            return;
        }

        const input = document.querySelector(root.dataset.definitionInput || '#definition');
        const form = root.closest('form');
        const preview = document.querySelector('[data-be-preview]');
        const addButton = document.querySelector('[data-be-add-part]');
        const reviewButton = document.querySelector('[data-be-review]');
        const data = readJson(document.getElementById('businessEntityData')?.textContent, {});
        const entities = data.entities || [];
        const models = data.models || [];
        const types = new Map((data.relationTypes || []).map(type => [type.name, type]));
        const urls = data.urls || {};
        const entityByUid = new Map(entities.map(entity => [entity.uid, entity]));
        const modelByKey = new Map(models.map(model => [model.key, model]));
        const tableColumns = new Map(); // pivot table => {exists, columns} | 'loading'

        let parts = fromDefinition(readJson(input?.value, {}));
        let confirmed = false;

        addButton?.addEventListener('click', openPicker);

        form?.addEventListener('submit', function (event) {
            syncInput();
            // Saving goes through the review step unless it was confirmed there.
            if (!confirmed && reviewButton) {
                event.preventDefault();
                review();
            }
        });

        render();

        /* ---------------- state ---------------- */

        function fromDefinition(definition) {
            const list = Array.isArray(definition?.parts) ? definition.parts : [];

            return list.map((part, index) => ({
                alias: String(part.alias || ''),
                source: part.model ? 'model' : 'entity',
                entity_uid: String(part.entity_uid || ''),
                model: String(part.model || ''),
                label: String(part.label || ''),
                link: index === 0 || !part.link ? null : normalizeLink(part.link),
                fields: (part.fields || []).map(field => String(field.field ?? field)),
                meta_fields: (part.meta_fields || []).map(field => String(field.meta_key ?? field)),
            }));
        }

        function normalizeLink(link) {
            const type = types.has(link.type) ? link.type : 'has_one';

            return {
                type,
                to: String(link.to || ''),
                mode: types.get(type).modes.includes(link.mode) ? link.mode : types.get(type).modes[0],
                foreign_key: String(link.foreign_key || ''),
                morph_type: String(link.morph_type || ''),
                morph_class: String(link.morph_class || ''),
                label_column: String(link.label_column || ''),
                pivot: {
                    table: String(link.pivot?.table || ''),
                    foreign_pivot_key: String(link.pivot?.foreign_pivot_key || ''),
                    related_pivot_key: String(link.pivot?.related_pivot_key || ''),
                    morph_type: String(link.pivot?.morph_type || ''),
                },
            };
        }

        /** A new part's link: to the primary entity by default. */
        function newLink(part) {
            const to = parts[0]?.alias || '';
            const type = part.source === 'model' ? 'belongs_to_many' : 'has_one';

            return normalizeLink({ type, to, mode: part.source === 'model' ? 'reference' : undefined });
        }

        function toDefinition() {
            return {
                version: 2,
                parts: parts.map((part, index) => {
                    const out = { alias: part.alias };

                    if (part.source === 'model') {
                        out.model = part.model;
                    } else {
                        out.entity_uid = part.entity_uid;
                    }

                    out.table = tableOf(part) || '';

                    if (part.label) {
                        out.label = part.label;
                    }

                    if (index > 0 && part.link) {
                        out.link = linkToDefinition(part);
                    }

                    out.fields = isReference(part) ? [] : part.fields.map(field => ({ field }));
                    out.meta_fields = isReference(part) ? [] : part.meta_fields.map(meta_key => ({ meta_key }));

                    return out;
                }),
            };
        }

        function linkToDefinition(part) {
            const link = part.link;
            const type = types.get(link.type);
            const out = { type: link.type, to: link.to, mode: link.mode };

            if (type.holder === 'pivot') {
                out.pivot = { table: link.pivot.table, foreign_pivot_key: link.pivot.foreign_pivot_key, related_pivot_key: link.pivot.related_pivot_key };
                if (type.morph) out.pivot.morph_type = link.pivot.morph_type;
            } else {
                out.foreign_key = link.foreign_key;
                if (type.morph) out.morph_type = link.morph_type;
            }

            if (type.morph && link.morph_class) out.morph_class = link.morph_class;
            if (link.mode === 'reference' && link.label_column) out.label_column = link.label_column;

            return out;
        }

        /* ---------------- structure helpers (mirror BusinessEntityDefinition) ---------------- */

        function sourceOf(part) {
            return part.source === 'model' ? modelByKey.get(part.model) : entityByUid.get(part.entity_uid);
        }

        function tableOf(part) {
            return sourceOf(part)?.table || '';
        }

        function nameOf(part) {
            return part.label || sourceOf(part)?.label || sourceOf(part)?.name || part.alias || 'part';
        }

        function columnsOf(part) {
            return sourceOf(part)?.columns || [];
        }

        function typeOf(part) {
            return part.link ? types.get(part.link.type) : null;
        }

        function isReference(part) {
            return part.link?.mode === 'reference';
        }

        function isTree(part) {
            const type = typeOf(part);

            return !type || (type.cardinality === 'one' && part.link.mode === 'compose');
        }

        /** Tree parts before index: what a link can point to. */
        function treeParts(index) {
            return parts.slice(0, index).filter(p => p.alias && isTree(p));
        }

        function partByAlias(alias) {
            return parts.find(p => p.alias === alias);
        }

        /** Columns a link fills in, so they can't be selected as fields. */
        function lockedColumns(part) {
            const columns = [];

            parts.forEach(other => {
                const type = typeOf(other);
                if (!type) return;

                if (isTree(other) && isTree(part)) {
                    if (type.holder === 'self' && other === part) columns.push(other.link.foreign_key);
                    if (type.holder === 'parent' && other.link.to === part.alias) columns.push(other.link.foreign_key);
                }
                if (other === part && type.holder === 'self') {
                    columns.push(other.link.foreign_key, type.morph ? other.link.morph_type : '');
                }
                if (isReference(other) && type.holder === 'parent' && other.link.to === part.alias) {
                    columns.push(other.link.foreign_key);
                }
            });

            return columns.filter(Boolean);
        }

        /** "Each Customer has many Notes" */
        function sentence(part) {
            const type = typeOf(part);
            const parent = partByAlias(part.link?.to);

            if (!type || !parent) {
                return '';
            }

            const a = nameOf(parent);
            const b = nameOf(part);
            const poly = type.morph ? ' (polymorphic)' : '';

            if (type.holder === 'pivot') {
                return type.morph === 'related'
                    ? `Each ${a} is assigned to many existing ${b}${poly}, picked in the form.`
                    : `Each ${a} is linked to many existing ${b}${poly}, picked in the form.`;
            }

            if (isReference(part)) {
                return `Each ${a} points to one existing ${b}, picked in the form.`;
            }

            if (type.holder === 'parent') {
                return `Each ${a} belongs to one ${b}, filled in on the same form (shared: kept when the ${a} is deleted).`;
            }

            return type.cardinality === 'many'
                ? `Each ${a} has many ${b}${poly}, added as rows in the form (deleted with the ${a}).`
                : `Each ${a} has one ${b}${poly}, filled in on the same form (deleted with the ${a}).`;
        }

        /** "notes.customer_id stores the Customer id" */
        function storageText(part) {
            const type = typeOf(part);
            const parent = partByAlias(part.link?.to);
            const link = part.link;

            if (!type || !parent) return '';

            if (type.holder === 'pivot') {
                const morph = type.morph ? `, ${link.pivot.table}.${link.pivot.morph_type || '?'} = the type` : '';
                return `${link.pivot.table || '?'}: ${link.pivot.foreign_pivot_key || '?'} → ${nameOf(parent)}, ${link.pivot.related_pivot_key || '?'} → ${nameOf(part)}${morph}`;
            }

            const holder = type.holder === 'self' ? tableOf(part) : tableOf(parent);
            const target = type.holder === 'self' ? nameOf(parent) : nameOf(part);
            const morph = type.morph ? ` (+ ${holder}.${link.morph_type || '?'} = the type)` : '';

            return `${holder || '?'}.${link.foreign_key || '?'} stores the ${target} id${morph}`;
        }

        function suggestAlias(source) {
            const base = String(source?.table || source?.key || source?.name || 'part')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '') || 'part';
            let alias = /^[a-z]/.test(base) ? base : `p_${base}`;
            const start = alias;
            let i = 2;

            while (parts.some(part => part.alias === alias) || ['id', 'uid', 'status', 'meta', 'parts'].includes(alias)) {
                alias = `${start}_${i++}`;
            }

            return alias;
        }

        /** Default link columns once the tables are known (customer → customer_id). */
        function suggestKeys(part) {
            const type = typeOf(part);
            const parent = partByAlias(part.link?.to);
            if (!type || !parent) return;

            const singular = name => String(name || '').replace(/ies$/, 'y').replace(/s$/, '');
            const parentKey = `${singular(tableOf(parent))}_id`;
            const ownKey = `${singular(tableOf(part))}_id`;
            const has = (cols, c) => cols.includes(c);

            if (type.holder === 'self' && !part.link.foreign_key && has(columnsOf(part), parentKey)) {
                part.link.foreign_key = parentKey;
            }
            if (type.holder === 'parent' && !part.link.foreign_key && has(columnsOf(parent), ownKey)) {
                part.link.foreign_key = ownKey;
            }
            if (type.holder === 'pivot' && !part.link.pivot.table) {
                const pair = [singular(tableOf(parent)), singular(tableOf(part))];
                part.link.pivot.table = type.morph ? `model_has_${tableOf(part)}` : [...pair].sort().join('_');
                part.link.pivot.foreign_pivot_key = type.morph ? 'model_id' : parentKey;
                part.link.pivot.related_pivot_key = ownKey;
                part.link.pivot.morph_type = type.morph ? 'model_type' : '';
                loadColumns(part.link.pivot.table);
            }
        }

        /* ---------------- picker (modal.js) ---------------- */

        function openPicker() {
            const used = new Set(parts.map(p => p.source === 'model' ? `model:${p.model}` : `entity:${p.entity_uid}`));
            const body = el('div', { className: 'be-picker' });
            const first = parts.length === 0;

            const primary = el('input', { type: 'checkbox', className: 'form-check-input', id: 'bePickPrimary', checked: first, disabled: first });
            body.append(el('div', { className: 'form-check mb-3' }, [
                primary,
                el('label', { className: 'form-check-label', htmlFor: 'bePickPrimary', textContent: 'Primary entity (the root: a record\'s uid is its uid)' }),
            ]));

            const search = el('input', { type: 'search', className: 'form-control form-control-sm mb-2', placeholder: 'Search entities…' });
            body.append(search);

            const list = el('div', { className: 'list-group be-picker-list' });
            const items = [];
            const addItem = (key, label, detail, disabled, reason, onPick) => {
                const item = el('button', { type: 'button', className: 'list-group-item list-group-item-action d-flex justify-content-between align-items-center', disabled });
                item.append(el('span', {}, [el('span', { className: 'fw-medium', textContent: label }), el('span', { className: 'small text-muted ms-2', textContent: detail })]));
                if (reason) item.append(el('span', { className: 'badge text-bg-light border', textContent: reason }));
                item.addEventListener('click', () => { onPick(primary.checked); closeModal(); });
                item.dataset.search = `${label} ${detail}`.toLowerCase();
                items.push(item);
                list.append(item);
            };

            list.append(el('div', { className: 'list-group-item small text-muted bg-body-tertiary', textContent: 'Entities' }));
            entities.forEach(entity => {
                const isUsed = used.has(`entity:${entity.uid}`);
                addItem(entity.uid, entity.name, entity.table || '', isUsed || !entity.published, isUsed ? 'already added' : (!entity.published ? 'not published' : ''),
                    asPrimary => addPart({ source: 'entity', entity_uid: entity.uid }, asPrimary));
            });

            if (models.length && !first) {
                list.append(el('div', { className: 'list-group-item small text-muted bg-body-tertiary', textContent: 'Platform models (reference only: pick existing records)' }));
                models.forEach(model => {
                    const isUsed = used.has(`model:${model.key}`);
                    addItem(model.key, model.label, model.table, isUsed, isUsed ? 'already added' : '',
                        () => addPart({ source: 'model', model: model.key }, false));
                });
            }

            body.append(list);

            search.addEventListener('input', () => {
                const q = search.value.trim().toLowerCase();
                items.forEach(item => item.classList.toggle('d-none', q !== '' && !item.dataset.search.includes(q)));
            });

            openModal('Add entity', body, []);
            setTimeout(() => search.focus(), 300);
        }

        function addPart(source, asPrimary) {
            const part = {
                alias: '',
                source: source.source,
                entity_uid: source.entity_uid || '',
                model: source.model || '',
                label: '',
                link: null,
                fields: [],
                meta_fields: [],
            };
            part.alias = suggestAlias(sourceOf(part));

            if (asPrimary || parts.length === 0) {
                // The new root; the old root now links to it.
                const oldRoot = parts[0];
                parts.unshift(part);
                if (oldRoot) {
                    oldRoot.link = normalizeLink({ type: 'belongs_to', to: part.alias });
                    suggestKeys(oldRoot);
                }
            } else {
                // Link before adding, so it points at an earlier part (not itself).
                part.link = newLink(part);
                parts.push(part);
                suggestKeys(part);
            }

            render();
        }

        function makePrimary(index) {
            const part = parts[index];
            parts.splice(index, 1);
            const oldRoot = parts[0];
            part.link = null;
            parts.unshift(part);
            // Links that pointed at this part now come before it: point them at the new root.
            parts.forEach((other, i) => {
                if (i > 0 && (!other.link || !treeParts(i).some(p => p.alias === other.link.to))) {
                    other.link = other.link || normalizeLink({ type: other === oldRoot ? 'belongs_to' : 'has_one', to: part.alias });
                    other.link.to = part.alias;
                }
            });
            render();
        }

        /* ---------------- review ---------------- */

        async function review() {
            syncInput();
            const body = el('div', { className: 'be-review' }, [el('div', { className: 'text-muted small', textContent: 'Checking against the database…' })]);
            openModal('Review business entity', body, []);

            let result;
            try {
                const response = await fetch(urls.check, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ definition: input.value }),
                });
                result = await response.json();
                if (!response.ok && !Array.isArray(result?.errors)) {
                    result = { valid: false, errors: Object.values(result?.errors || { e: [result?.message || 'Check failed.'] }).flat() };
                }
            } catch (e) {
                result = { valid: false, errors: ['The definition could not be checked. Try again.'] };
            }

            body.replaceChildren(...reviewContent(result));
            setModalActions(result.valid ? [{
                label: 'Confirm &amp; save',
                className: 'btn btn-sm btn-primary',
                action: () => { confirmed = true; closeModal(); form.requestSubmit ? form.requestSubmit() : form.submit(); },
            }] : []);
        }

        function reviewContent(result) {
            const nodes = [];

            if (!result.valid) {
                const alert = el('div', { className: 'alert alert-danger small' }, [el('div', { className: 'fw-medium mb-1', textContent: 'Fix these before saving:' })]);
                const ul = el('ul', { className: 'mb-0 ps-3' });
                (result.errors || []).forEach(error => ul.append(el('li', { textContent: error })));
                alert.append(ul);
                nodes.push(alert);
                (result.missing_pivots || []).forEach(pivot => nodes.push(pivotHelp(pivot.table, pivot.columns)));
            } else {
                nodes.push(el('div', { className: 'alert alert-success small py-2', textContent: 'Everything this business entity uses exists. Saving stores the definition and generates its form and table; no tables are created or changed.' }));
            }

            const tree = el('ul', { className: 'be-tree list-unstyled small mb-0' });
            parts.forEach((part, index) => {
                const li = el('li', { className: 'mb-2' + (index ? ' ms-3' : '') });
                li.append(el('div', {}, [
                    el('span', { className: 'badge me-1 text-bg-' + (index === 0 ? 'primary' : (isReference(part) ? 'info' : 'secondary')), textContent: index === 0 ? 'Primary' : (isReference(part) ? 'Reference' : (typeOf(part)?.cardinality === 'many' ? 'List' : 'Part')) }),
                    el('span', { className: 'fw-medium', textContent: `${nameOf(part)} ` }),
                    el('code', { textContent: part.alias }),
                    el('span', { className: 'text-muted', textContent: ` · ${tableOf(part)}` }),
                ]));
                if (index > 0) {
                    li.append(el('div', { textContent: sentence(part) }));
                    li.append(el('div', { className: 'text-muted' }, [el('i', { className: 'fas fa-fw fa-database me-1' }), document.createTextNode(storageText(part))]));
                }
                const fields = [...part.fields, ...part.meta_fields.map(m => `${m} (meta)`)];
                if (!isReference(part)) {
                    li.append(el('div', { className: 'text-muted', textContent: fields.length ? `Fields: ${fields.join(', ')}` : 'No fields' }));
                }
                tree.append(li);
            });
            nodes.push(tree);

            return nodes;
        }

        function pivotHelp(table, columns) {
            const box = el('div', { className: 'alert alert-warning small' });
            box.append(el('div', { className: 'fw-medium', textContent: `Needs pivot table "${table}"` }));
            box.append(el('div', { textContent: `Create it as an entity with the columns ${columns.join(', ')}, publish it, then come back. A business entity never creates tables.` }));
            if (urls.createEntity) {
                const link = el('a', { href: urls.createEntity, target: '_blank', rel: 'noopener', className: 'btn btn-sm btn-outline-dark mt-2' }, [el('i', { className: 'fas fa-fw fa-plus me-1' }), document.createTextNode('Create pivot entity')]);
                box.append(link);
            }

            return box;
        }

        /* ---------------- rendering ---------------- */

        function render() {
            if (!parts.length) {
                root.replaceChildren(el('div', { className: 'be-empty text-muted small border rounded p-3', textContent: 'Start with "Add entity": the first one is the primary entity.' }));
            } else {
                root.replaceChildren(...parts.map(renderPart));
            }
            renderPreview();
            syncInput();
        }

        function renderPart(part, index) {
            const source = sourceOf(part);
            const card = el('div', { className: 'card be-part mb-2' });
            const body = el('div', { className: 'card-body' });

            const title = el('h6', { className: 'fs-6 mb-0' }, [
                el('span', { className: 'badge me-2 text-bg-' + (index === 0 ? 'primary' : 'secondary'), textContent: index === 0 ? 'Primary' : `Part ${index + 1}` }),
                document.createTextNode(source ? (source.name || source.label) : 'Unknown source'),
                el('span', { className: 'small text-muted ms-2', textContent: tableOf(part) }),
            ]);
            const actions = el('div', { className: 'd-flex gap-1' });

            if (index > 0 && part.source === 'entity') {
                const primary = el('button', { type: 'button', className: 'btn btn-sm btn-outline-secondary', title: 'Make primary' }, [el('i', { className: 'fas fa-fw fa-crown' })]);
                primary.addEventListener('click', () => makePrimary(index));
                actions.append(primary);
            }

            if (index > 0 || parts.length === 1) {
                const remove = el('button', { type: 'button', className: 'btn btn-sm btn-outline-danger', title: 'Remove' }, [el('i', { className: 'fas fa-fw fa-trash' })]);
                remove.addEventListener('click', () => removePart(index));
                actions.append(remove);
            }

            body.append(el('div', { className: 'd-flex justify-content-between align-items-center gap-2 mb-2' }, [title, actions]));

            if (index > 0 && part.link) {
                body.append(el('p', { className: 'be-sentence small mb-2', textContent: sentence(part) }));
            }

            const row = el('div', { className: 'row g-2' });
            row.append(col('col-6 col-md-4', labelled('Alias', aliasInput(part))));
            row.append(col('col-6 col-md-4', labelled('Label', textInput(part.label, 'optional', value => { part.label = value; renderPreview(); syncInput(); }))));
            body.append(row);

            if (index > 0 && part.link) {
                body.append(renderLink(part, index));
            }

            if (source && !isReference(part)) {
                body.append(renderFieldChoices(part, source));
            }

            card.append(body);

            return card;
        }

        function aliasInput(part) {
            const field = textInput(part.alias, 'e.g. customer', value => {
                const next = value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                renameLinks(part.alias, next);
                part.alias = next;
                field.classList.toggle('is-invalid', !isValidAlias(next) || parts.filter(p => p.alias === next).length > 1);
                renderPreview();
                syncInput();
            });
            field.classList.toggle('is-invalid', part.alias !== '' && !isValidAlias(part.alias));
            field.addEventListener('change', render);

            return field;
        }

        function renderLink(part, index) {
            const wrap = el('div', { className: 'be-link border-top pt-2 mt-2' });
            const link = part.link;
            const type = typeOf(part);
            const parent = partByAlias(link.to);
            const row = el('div', { className: 'row g-2' });

            // Relation type: what the user means, in words.
            const typeSelect = el('select', { className: 'form-select form-select-sm' });
            types.forEach(t => {
                const allowed = part.source !== 'model' || t.modes.includes('reference');
                typeSelect.append(el('option', { value: t.name, textContent: `${t.label} (${t.name})`, selected: t.name === link.type, disabled: !allowed }));
            });
            typeSelect.addEventListener('change', () => {
                const next = normalizeLink({ type: typeSelect.value, to: link.to, mode: part.source === 'model' ? 'reference' : undefined });
                part.link = next;
                suggestKeys(part);
                render();
            });
            row.append(col('col-12 col-md-5', labelled('Relation', typeSelect)));

            const to = el('select', { className: 'form-select form-select-sm' });
            const targets = treeParts(index);
            targets.forEach(other => to.append(el('option', { value: other.alias, textContent: `${nameOf(other)} (${other.alias})`, selected: other.alias === link.to })));
            if (!targets.some(other => other.alias === link.to)) {
                link.to = targets[0]?.alias || '';
            }
            to.addEventListener('change', () => { link.to = to.value; link.foreign_key = ''; suggestKeys(part); render(); });
            row.append(col('col-6 col-md-4', labelled('Belongs to record of', to)));

            if (type.modes.length > 1 && part.source !== 'model') {
                const mode = el('select', { className: 'form-select form-select-sm' }, type.modes.map(m => el('option', {
                    value: m, selected: m === link.mode,
                    textContent: m === 'compose' ? 'Fill in on the form' : 'Pick an existing record',
                })));
                mode.addEventListener('change', () => { link.mode = mode.value; render(); });
                row.append(col('col-6 col-md-3', labelled('How', mode)));
            }
            wrap.append(row);

            const keys = el('div', { className: 'row g-2 mt-1' });
            if (type.holder === 'pivot') {
                keys.append(...pivotFields(part, parent));
            } else {
                const holder = type.holder === 'self' ? part : parent;
                const holderTable = holder ? tableOf(holder) : '';
                keys.append(col('col-6 col-md-4', labelled(`Link column in ${holderTable || '…'}`, columnSelect(holder ? columnsOf(holder) : [], link.foreign_key, value => {
                    link.foreign_key = value;
                    parts.forEach(p => { p.fields = p.fields.filter(name => !lockedColumns(p).includes(name)); });
                    render();
                }))));
                if (type.morph) {
                    keys.append(col('col-6 col-md-4', labelled(`Type column in ${holderTable || '…'}`, columnSelect(holder ? columnsOf(holder) : [], link.morph_type, value => { link.morph_type = value; render(); }))));
                }
            }

            if (type.morph) {
                keys.append(col('col-6 col-md-4', labelled('Type value', textInput(link.morph_class, type.morph === 'parent' ? (parent ? `default: ${tableOf(parent)}` : '') : `default: ${tableOf(part)}`, value => { link.morph_class = value; syncInput(); }))));
            }

            if (link.mode === 'reference') {
                keys.append(col('col-6 col-md-4', labelled('Show records by', columnSelect(columnsOf(part), link.label_column, value => { link.label_column = value; syncInput(); }, 'Automatic'))));
            }

            wrap.append(keys);
            wrap.append(el('div', { className: 'form-text', textContent: storageText(part) }));

            return wrap;
        }

        function pivotFields(part, parent) {
            const link = part.link;
            const type = typeOf(part);
            const info = tableColumns.get(link.pivot.table);
            const columns = info && info !== 'loading' ? info.columns : [];
            const nodes = [];

            const table = textInput(link.pivot.table, 'e.g. customer_tag', value => { link.pivot.table = value; syncInput(); });
            table.setAttribute('list', 'beKnownTables');
            table.addEventListener('change', () => { loadColumns(link.pivot.table); render(); });
            nodes.push(col('col-12 col-md-4', labelled('Pivot table', table)));
            nodes.push(col('col-6 col-md-4', labelled(`${parent ? nameOf(parent) : 'Record'} id column`, columnSelect(columns, link.pivot.foreign_pivot_key, value => { link.pivot.foreign_pivot_key = value; render(); }))));
            nodes.push(col('col-6 col-md-4', labelled(`${nameOf(part)} id column`, columnSelect(columns, link.pivot.related_pivot_key, value => { link.pivot.related_pivot_key = value; render(); }))));
            if (type.morph) {
                nodes.push(col('col-6 col-md-4', labelled('Type column', columnSelect(columns, link.pivot.morph_type, value => { link.pivot.morph_type = value; render(); }))));
            }

            if (link.pivot.table && info === undefined) {
                loadColumns(link.pivot.table);
            } else if (info && info !== 'loading' && !info.exists) {
                const needed = [link.pivot.foreign_pivot_key, link.pivot.related_pivot_key, type.morph ? link.pivot.morph_type : ''].filter(Boolean);
                nodes.push(el('div', { className: 'col-12' }, [pivotHelp(link.pivot.table, needed.length ? needed : ['…_id', '…_id'])]));
            }

            return nodes;
        }

        function loadColumns(table) {
            if (!table || tableColumns.has(table) || !urls.columns) return;
            tableColumns.set(table, 'loading');
            fetch(`${urls.columns}?table=${encodeURIComponent(table)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json())
                .then(result => { tableColumns.set(table, { exists: Boolean(result.exists), columns: result.columns || [] }); render(); })
                .catch(() => tableColumns.delete(table));
        }

        function columnSelect(columns, value, onChange, emptyLabel = 'Select column…') {
            const select = el('select', { className: 'form-select form-select-sm' });
            select.append(el('option', { value: '', textContent: emptyLabel }));
            const list = value && !columns.includes(value) ? [value, ...columns] : columns;
            list.forEach(column => select.append(el('option', { value: column, textContent: column, selected: column === value })));
            select.classList.toggle('is-invalid', emptyLabel === 'Select column…' && !value);
            select.addEventListener('change', () => onChange(select.value));

            return select;
        }

        function renderFieldChoices(part, source) {
            const wrap = el('div', { className: 'mt-2' });
            const blocked = lockedColumns(part);
            const fields = (source.fields || []).filter(field => !field.system && !blocked.includes(field.name));
            const many = typeOf(part)?.cardinality === 'many';

            wrap.append(checkboxGroup(many ? 'Columns of each row' : 'Fields', fields.map(field => ({ value: field.name, label: field.label, hint: field.type })), part.fields, values => { part.fields = values; }));

            if ((source.meta_fields || []).length) {
                wrap.append(checkboxGroup('Meta fields', source.meta_fields.map(field => ({ value: field.meta_key, label: field.label, hint: field.type })), part.meta_fields, values => { part.meta_fields = values; }));
            }

            return wrap;
        }

        function checkboxGroup(title, options, selected, onChange) {
            const group = el('div', { className: 'be-fields mb-1' });
            group.append(el('div', { className: 'small text-muted mb-1', textContent: title }));

            if (!options.length) {
                group.append(el('div', { className: 'small text-muted fst-italic', textContent: 'None available' }));

                return group;
            }

            const list = el('div', { className: 'd-flex flex-wrap gap-2' });
            options.forEach(option => {
                const id = `be-${Math.random().toString(36).slice(2)}`;
                const checkbox = el('input', { type: 'checkbox', className: 'btn-check', id, checked: selected.includes(option.value) });
                const label = el('label', { className: 'btn btn-sm btn-outline-secondary', htmlFor: id, title: option.hint || '' }, [document.createTextNode(option.label)]);

                checkbox.addEventListener('change', () => {
                    const values = new Set(selected);
                    checkbox.checked ? values.add(option.value) : values.delete(option.value);
                    selected.splice(0, selected.length, ...options.map(o => o.value).filter(v => values.has(v)));
                    onChange([...selected]);
                    renderPreview();
                    syncInput();
                });

                list.append(checkbox, label);
            });
            group.append(list);

            return group;
        }

        function renderPreview() {
            if (!preview) return;

            const items = [];
            parts.forEach(part => {
                if (isTree(part)) {
                    part.fields.forEach(name => items.push([`${part.alias}${SEPARATOR}${name}`, '']));
                    part.meta_fields.forEach(name => items.push([`${part.alias}${SEPARATOR}${name}`, 'meta']));
                } else if (isReference(part)) {
                    items.push([part.alias, typeOf(part)?.cardinality === 'many' ? 'pick many' : 'pick one']);
                } else {
                    items.push([part.alias, `rows: ${[...part.fields, ...part.meta_fields].join(', ') || '—'}`]);
                }
            });

            if (!items.length) {
                preview.replaceChildren(el('li', { className: 'text-muted fst-italic', textContent: 'No fields selected yet' }));

                return;
            }

            preview.replaceChildren(...items.map(([key, badge]) => el('li', {}, [
                el('code', { textContent: key }),
                badge ? el('span', { className: 'badge text-bg-light border ms-1', textContent: badge }) : document.createTextNode(''),
            ])));
        }

        /* ---------------- helpers ---------------- */

        function removePart(index) {
            const alias = parts[index].alias;
            parts.splice(index, 1);
            if (index === 0 && parts[0]) {
                parts[0].link = null;
            }
            parts.forEach((part, i) => {
                if (i > 0 && part.link && part.link.to === alias) {
                    part.link.to = parts[0]?.alias || '';
                    part.link.foreign_key = '';
                }
            });
            render();
        }

        function renameLinks(from, to) {
            if (!from || from === to) return;
            parts.forEach(part => { if (part.link && part.link.to === from) part.link.to = to; });
        }

        function isValidAlias(alias) {
            return /^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/.test(alias) && alias.length <= 40;
        }

        function syncInput() {
            if (input) input.value = JSON.stringify(toDefinition());
        }

        /** modal.js (iquesters/user-interface) when loaded, else an inline panel. */
        function openModal(title, body, actions) {
            if (typeof showModal === 'function') {
                showModal({
                    header: { enabled: true, content: `<h6 class="modal-title fs-6">${escapeText(title)}</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>` },
                    body: { enabled: true, content: body },
                    footer: { enabled: true, allowCancel: true, actions },
                });
                return;
            }

            const panel = document.getElementById('beInlinePanel');
            if (panel) {
                panel.replaceChildren(el('h6', { className: 'fs-6', textContent: title }), body);
                panel.classList.remove('d-none');
            }
        }

        function setModalActions(actions) {
            const footer = document.getElementById('labModalFooterActions');
            if (footer && typeof processModalActions === 'function') {
                footer.replaceChildren();
                processModalActions(actions);
            } else if (actions.length) {
                const panel = document.getElementById('beInlinePanel');
                actions.forEach(action => {
                    const button = el('button', { type: 'button', className: action.className, textContent: action.label.replace('&amp;', '&') });
                    button.addEventListener('click', action.action);
                    panel?.append(button);
                });
            }
        }

        function closeModal() {
            const modal = document.getElementById('labModal');
            if (modal && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            }
            document.getElementById('beInlinePanel')?.classList.add('d-none');
        }

        function escapeText(text) {
            const span = document.createElement('span');
            span.textContent = text;
            return span.innerHTML;
        }

        function csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form?.querySelector('input[name="_token"]')?.value || '';
        }

        function readJson(text, fallback) {
            try {
                return text ? JSON.parse(text) : fallback;
            } catch (e) {
                return fallback;
            }
        }

        function el(tag, props = {}, children = []) {
            const node = document.createElement(tag);
            Object.entries(props).forEach(([key, value]) => {
                if (key === 'htmlFor') node.htmlFor = value;
                else if (key in node) node[key] = value;
                else node.setAttribute(key, value);
            });
            children.forEach(child => node.append(child));

            return node;
        }

        function col(className, child) {
            return el('div', { className }, [child]);
        }

        function labelled(text, control) {
            const id = control.id || `be-${Math.random().toString(36).slice(2)}`;
            control.id = id;

            return el('div', {}, [el('label', { className: 'form-label small mb-1', htmlFor: id, textContent: text }), control]);
        }

        function textInput(value, placeholder, onInput) {
            const field = el('input', { type: 'text', className: 'form-control form-control-sm', value: value || '', placeholder });
            field.addEventListener('input', () => onInput(field.value.trim()));

            return field;
        }
    });
})();
