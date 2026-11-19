/**
 * Business entity builder (foundation admin, business_entity/create-edit).
 *
 * Edits the definition kept in the hidden #definition input:
 *   { version: 1, parts: [ { alias, entity_uid, table, label?, link?, fields: [{field}], meta_fields: [{meta_key}] } ] }
 * The first part is the root; every other part links to an earlier part:
 *   has_one    this part's table holds the foreign key (→ the other part's id)
 *   belongs_to the other part's table holds the foreign key (→ this part's id)
 * The server validates everything again (BusinessEntityDefinitionValidator).
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
        const entities = readJson(document.getElementById('businessEntityEntities')?.textContent, []);
        const entityByUid = new Map(entities.map(entity => [entity.uid, entity]));

        let parts = fromDefinition(readJson(input?.value, {}));

        addButton?.addEventListener('click', function () {
            parts.push(emptyPart(parts.length === 0));
            render();
        });

        form?.addEventListener('submit', function () {
            input.value = JSON.stringify(toDefinition());
        });

        if (parts.length === 0) {
            parts.push(emptyPart(true));
        }

        render();

        /* ---------------- state ---------------- */

        function emptyPart(isRoot) {
            return {
                alias: '',
                entity_uid: '',
                label: '',
                link: isRoot ? null : { type: 'has_one', to: parts[0]?.alias || '', foreign_key: '' },
                fields: [],
                meta_fields: [],
            };
        }

        function fromDefinition(definition) {
            const list = Array.isArray(definition?.parts) ? definition.parts : [];

            return list.map((part, index) => ({
                alias: String(part.alias || ''),
                entity_uid: String(part.entity_uid || ''),
                label: String(part.label || ''),
                link: index === 0 ? null : {
                    type: part.link?.type === 'belongs_to' ? 'belongs_to' : 'has_one',
                    to: String(part.link?.to || ''),
                    foreign_key: String(part.link?.foreign_key || ''),
                },
                fields: (part.fields || []).map(field => String(field.field ?? field)),
                meta_fields: (part.meta_fields || []).map(field => String(field.meta_key ?? field)),
            }));
        }

        function toDefinition() {
            return {
                version: 1,
                parts: parts.map((part, index) => {
                    const out = {
                        alias: part.alias,
                        entity_uid: part.entity_uid,
                        table: entityByUid.get(part.entity_uid)?.table || '',
                        fields: part.fields.map(field => ({ field })),
                        meta_fields: part.meta_fields.map(meta_key => ({ meta_key })),
                    };

                    if (part.label) {
                        out.label = part.label;
                    }

                    if (index > 0 && part.link) {
                        out.link = { ...part.link };
                    }

                    return out;
                }),
            };
        }

        /** The table that holds this part's link column. */
        function foreignKeyEntity(part) {
            if (!part.link) {
                return null;
            }

            const owner = part.link.type === 'has_one' ? part : parts.find(p => p.alias === part.link.to);

            return owner ? entityByUid.get(owner.entity_uid) : null;
        }

        /** Link columns filled in automatically for a part (not selectable as fields). */
        function linkColumns(part) {
            const columns = [];

            parts.forEach(other => {
                if (!other.link) return;
                if (other.link.type === 'has_one' && other === part) columns.push(other.link.foreign_key);
                if (other.link.type === 'belongs_to' && other.link.to === part.alias) columns.push(other.link.foreign_key);
            });

            return columns.filter(Boolean);
        }

        function suggestAlias(entity) {
            const base = String(entity?.table || entity?.name || 'part')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .replace(/ies$/, 'y')
                .replace(/s$/, '') || 'part';
            let alias = /^[a-z]/.test(base) ? base : `p_${base}`;
            let i = 2;

            while (parts.some(part => part.alias === alias)) {
                alias = `${base}_${i++}`;
            }

            return alias;
        }

        /* ---------------- rendering ---------------- */

        function render() {
            root.replaceChildren(...parts.map(renderPart));
            renderPreview();
            input.value = JSON.stringify(toDefinition());
        }

        function renderPart(part, index) {
            const entity = entityByUid.get(part.entity_uid);
            const card = el('div', { className: 'card be-part mb-2' });
            const body = el('div', { className: 'card-body' });

            const header = el('div', { className: 'd-flex justify-content-between align-items-center gap-2 mb-2' }, [
                el('h6', { className: 'fs-6 mb-0' }, [
                    el('span', { className: 'badge text-bg-' + (index === 0 ? 'primary' : 'secondary') + ' me-2', textContent: index === 0 ? 'Root' : `Part ${index + 1}` }),
                    document.createTextNode(entity ? entity.name : 'Choose an entity'),
                ]),
            ]);

            if (index > 0) {
                const remove = el('button', { type: 'button', className: 'btn btn-sm btn-outline-danger', title: 'Remove part' }, [icon('fas fa-fw fa-trash')]);
                remove.addEventListener('click', () => removePart(index));
                header.append(remove);
            }

            body.append(header);

            const row = el('div', { className: 'row g-2' });
            row.append(col('col-12 col-md-5', labelled('Entity', entitySelect(part))));
            row.append(col('col-6 col-md-4', labelled('Alias', aliasInput(part))));
            row.append(col('col-6 col-md-3', labelled('Label', textInput(part.label, 'optional', value => { part.label = value; renderPreview(); syncInput(); }))));
            body.append(row);

            if (index > 0) {
                body.append(renderLink(part, index));
            }

            if (entity) {
                body.append(renderFieldChoices(part, entity));
            }

            card.append(body);

            return card;
        }

        function entitySelect(part) {
            const select = el('select', { className: 'form-select form-select-sm' });
            select.append(el('option', { value: '', textContent: 'Select…' }));

            entities.forEach(entity => {
                const option = el('option', {
                    value: entity.uid,
                    textContent: entity.published ? entity.name : `${entity.name} (not published)`,
                    disabled: !entity.published,
                    selected: entity.uid === part.entity_uid,
                });
                select.append(option);
            });

            select.addEventListener('change', () => {
                part.entity_uid = select.value;
                part.fields = [];
                part.meta_fields = [];
                if (part.link) {
                    part.link.foreign_key = '';
                }
                // Suggest an alias only when none was typed.
                if (!part.alias && select.value) {
                    part.alias = suggestAlias(entityByUid.get(select.value));
                }
                render();
            });

            return select;
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
            const wrap = el('div', { className: 'be-link row g-2 mt-1' });
            const earlier = parts.slice(0, index).filter(p => p.alias);

            const type = el('select', { className: 'form-select form-select-sm' }, [
                el('option', { value: 'has_one', textContent: 'has one: this table holds the link column', selected: part.link.type === 'has_one' }),
                el('option', { value: 'belongs_to', textContent: 'belongs to: the other table holds the link column', selected: part.link.type === 'belongs_to' }),
            ]);
            type.addEventListener('change', () => { part.link.type = type.value; part.link.foreign_key = ''; render(); });

            const to = el('select', { className: 'form-select form-select-sm' });
            earlier.forEach(other => to.append(el('option', { value: other.alias, textContent: other.alias, selected: other.alias === part.link.to })));
            if (!earlier.some(other => other.alias === part.link.to)) {
                part.link.to = earlier[0]?.alias || '';
            }
            to.addEventListener('change', () => { part.link.to = to.value; part.link.foreign_key = ''; render(); });

            const fkEntity = foreignKeyEntity(part);
            const fk = el('select', { className: 'form-select form-select-sm' });
            fk.append(el('option', { value: '', textContent: fkEntity ? 'Select column…' : 'Choose entities first' }));
            (fkEntity?.fields || []).filter(field => !field.system).forEach(field => {
                fk.append(el('option', { value: field.name, textContent: `${fkEntity.table}.${field.name}`, selected: field.name === part.link.foreign_key }));
            });
            fk.classList.toggle('is-invalid', !part.link.foreign_key);
            fk.addEventListener('change', () => {
                part.link.foreign_key = fk.value;
                // The link column is filled in automatically: unselect it as a form field.
                parts.forEach(p => { p.fields = p.fields.filter(name => !linkColumns(p).includes(name)); });
                render();
            });

            wrap.append(col('col-12 col-md-5', labelled('Link', type)));
            wrap.append(col('col-6 col-md-3', labelled('To part', to)));
            wrap.append(col('col-6 col-md-4', labelled('Link column', fk)));

            return wrap;
        }

        function renderFieldChoices(part, entity) {
            const wrap = el('div', { className: 'mt-2' });
            const blocked = linkColumns(part);
            const fields = (entity.fields || []).filter(field => !field.system && !blocked.includes(field.name));

            wrap.append(checkboxGroup('Fields', fields.map(field => ({ value: field.name, label: field.label, hint: field.type })), part.fields, values => { part.fields = values; }));

            if ((entity.meta_fields || []).length) {
                wrap.append(checkboxGroup('Meta fields', entity.meta_fields.map(field => ({ value: field.meta_key, label: field.label, hint: field.type })), part.meta_fields, values => { part.meta_fields = values; }));
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
                part.fields.forEach(name => items.push([`${part.alias}${SEPARATOR}${name}`, false]));
                part.meta_fields.forEach(name => items.push([`${part.alias}${SEPARATOR}${name}`, true]));
            });

            if (!items.length) {
                preview.replaceChildren(el('li', { className: 'text-muted fst-italic', textContent: 'No fields selected yet' }));

                return;
            }

            preview.replaceChildren(...items.map(([key, meta]) => el('li', {}, [
                el('code', { textContent: key }),
                meta ? el('span', { className: 'badge text-bg-light border ms-1', textContent: 'meta' }) : document.createTextNode(''),
            ])));
        }

        /* ---------------- helpers ---------------- */

        function removePart(index) {
            const alias = parts[index].alias;
            parts.splice(index, 1);
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
            input.value = JSON.stringify(toDefinition());
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

        function icon(className) {
            return el('i', { className });
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
