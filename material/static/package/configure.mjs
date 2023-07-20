import { createApp } from '../vue.mjs';

const initial_config = JSON.parse(document.getElementById('config_json').textContent);
const file_tree = JSON.parse(document.getElementById('files_json').textContent);

const DirectoryTree = {
    props: ['modelValue', 'tree', 'parents'],
    emits: ['update:modelValue'],
    computed: {
        selected() {
            return typeof this.modelValue == 'string' ? [''].concat(this.modelValue.split('/')) : (this.modelValue || []);
        },
        display_path() {
            return this.tree.path || (this.modelValue || 'Nothing selected');
        },
        parents_path() {
            const parents = this.parents || [];
            return this.tree.path ? parents.concat(this.tree.path) : parents;
        },
        selected_path() {
            return this.selected.slice(1).join('/');
        },
        in_selected() {
            return this.tree.path != '' && (this.selected && this.selected[0] == this.tree.path);
        },
    },
    methods: {
        file_selected(file) {
            return this.selected?.length==2 && this.selected[1] == file;
        }
    },
    template: `
        <details class="directory-tree" :class="{selected: in_selected}" :open="in_selected || null" role="tree">
            <summary><span v-if="tree.path">{{tree.path}}</span><code class="file-path" v-else-if="selected_path">{{selected_path}}</code><span v-else>Select a file</span></summary>
            <ul>
                <li v-for="dir in tree.dirs"><DirectoryTree :tree="dir" :parents="parents_path" :modelValue="selected.slice(2).join('/')" @update:modelValue="$emit('update:modelValue',$event)"/></li>
                <li v-for="file in tree.files">
                    <button type="button" class="file" :class="{selected: file_selected(file)}" :aria-current="file_selected(file) || null" @click="$emit('update:modelValue', parents_path.concat([file]).join('/'))">{{file}}</button>
                </li>
            </ul>
        </details>
    `
}

const StructureItemTree = {
    props: ['modelValue', 'current_tab', 'parent_list'],
    emits: ['select-item', 'add-item'],
    computed: {
        is_selected() {
            return this.modelValue == this.current_tab?.item;
        },
        children() {
            return this.modelValue.content || [];
        }
    },
    template: `
        <button type="button" class="item" role="tab" :aria-expanded="children.length>0 || null" :aria-current="is_selected || null" @click="$emit('select-item',{item:modelValue, parent_list})">
            <span class="item-type"><ItemTypeName :type="modelValue.type"/></span>
            <br>
            {{modelValue.title || 'Unnamed item'}}
        </button>
        <ul class="content" v-if="children.length>0 || modelValue.type=='part'">
            <li role="none" v-for="item in children">
                <Structure-Item-Tree :current_tab="current_tab" :parent_list="children" v-model="item" @select-item="$emit('select-item',$event)"/>
            </li>
            <li v-if="modelValue.type == 'part'">
                <button class="action add-item" type="button" @click="$emit('add-item',modelValue)">+ Add an item</button>
            </li>
        </ul>
    `
};

const ItemTypeName = {
    props: ['type'],
    computed: {
        nice_name() {
            const item_types = {
                'introduction': 'Introduction',
                'part': 'Part',
                'document': 'Document',
                'chapter': 'Chapter',
                'standalone': 'Standalone',
                'url': 'URL',
                'html': 'HTML',
                'slides': 'Slides',
                'exam': 'Exam',
                'notebook': 'Notebook'
            }
            return item_types[this.type] || `Unknown item type "${this.type}"`;
        }
    },
    template: `{{nice_name}}`
}

const StructureItemEditor = {
    props: ['modelValue', 'file_tree'],
    emits: ['delete-item'],
    data() {
        return {
            use_custom_slug: false,
            item_types: [
                'introduction',
                'part',
                'document',
                'chapter',
                'standalone',
                'url',
                'html',
                'slides',
                'exam',
                'notebook'
            ]
        }
    },

    computed: {
        item() {
            return this.modelValue?.item;
        },

        parent_list() {
            return this.modelValue?.parent_list;
        },

        json() {
            return JSON.stringify(this.item);
        },
        source() {
            return [''].concat(this.item.source?.split('/'));
        },

        source_kind() {
            const source_kinds = {
                'url': 'string',
                'html': 'none',
                'part': 'none',
            }
            return source_kinds[this.modelValue.item.type] || 'file';
        },

        has_custom_slug() {
            return this.item.slug || this.use_custom_slug;
        },
    },

    methods: {
        set_source(path) {
            this.item.source = path.join('/');
        }
    },

    template: `
        <button class="delete" type="button" @click="$emit('delete-item', modelValue)">Delete this item</button>

        <fieldset>
            <legend>Metadata</legend>
            <label for="item-type">Type</label>
            <select id="item-type" v-model="item.type">
                <option v-for="type in item_types" :value="type"><ItemTypeName :type="type"/></option>
            </select>

            <label for="item-title">Title</label>
            <input id="item-title" v-model="item.title">

            <label for="item-slug" v-if="has_custom_slug">Slug</label>
            <input id="item-slug" v-if="has_custom_slug" v-model="item.slug">
            <label for="item-author">Author</label>
            <input id="item-author" v-model="item.author">
        </fieldset>

        <fieldset>
            <legend>Source</legend>

            <label for="item-source" v-if="source_kind != 'none'">Source</label>
            <DirectoryTree id="item-source" v-if="source_kind == 'file'" :tree="file_tree" v-model="item.source"/>

            <div v-if="source_kind == 'string'">
                <input id="item-source" v-model="item.source">
                <p class="input-hint">A URL</p>
            </div>

            <label for="item-html" v-if="item?.type=='html'">HTML content</label>
            <textarea v-if="item?.type=='html'" id="item-html" v-model="item.html"></textarea>

            <label for="item-thumbnail">Thumbnail file</label>
            <DirectoryTree id="item-thumbnail" :tree="file_tree" v-model="item.thumbnail"/>

        </fieldset>

        <fieldset>
            <legend>Display options</legend>
            <label for="item-is_hidden">Hidden?</label>
            <input id="item-is_hidden" type="checkbox" v-model="item.is_hidden">

            <label for="item-build_pdf">Build PDF?</label>
            <input id="item-build_pdf" type="checkbox" :checked="item.build_pdf ?? true" @change="item.build_pdf = $event.target.checked">

            <template v-if="item.build_pdf ?? true">
                <label for="item-pdf_url">PDF URL</label>
                <input id="item-pdf_url" v-model="item.pdf_url">
            </template>


            <label for="item-sidebar">Show the sidebar?</label>
            <input id="item-sidebar" type="checkbox" :checked="item.sidebar ?? true" @change="item.sidebar = $event.target.checked">

            <label for="item-topbar">Show the top bar?</label>
            <input id="item-topbar" type="checkbox" :checked="item.topbar ?? true" @change="item.topbar = $event.target.checked">

            <label for="item-footer">Show the footer?</label>
            <input id="item-footer" type="checkbox" :checked="item.footer ?? true" @change="item.footer = $event.target.checked">
        </fieldset>
    `
}

const app = createApp({
    data() {
        return {
            tab: 'package-settings',
            file_tree,
            config: initial_config || {structure: []}
        }
    },
    methods: {
        select_item(item) {
            this.tab = item;
        },

        add_item(parent) {
            let parent_list;
            if(!parent) {
                this.config.structure = this.config.structure || [];
                parent_list = this.config.structure;
            } else {
                parent_list = parent.content = parent.content || [];
            }
            const item = {type: 'chapter'};
            parent_list.push(item);
            this.tab = {item, parent_list};
        },

        delete_item({item, parent_list}) {
            console.log(item, parent_list);
            parent_list.splice(parent_list.indexOf(item),1);
            this.tab = null;
        }
    }
});

app.component('DirectoryTree', DirectoryTree);
app.component('StructureItemTree', StructureItemTree);
app.component('StructureItemEditor', StructureItemEditor);
app.component('ItemTypeName', ItemTypeName);

const app_instance = window.app = app.mount('#app');

document.querySelector('#config-form').addEventListener('submit', e => {
    document.querySelector('#id_config').value = JSON.stringify(app_instance.config);
});
