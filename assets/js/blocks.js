(function (blocks, element, blockEditor, components, i18n) {
    if (!blocks || !element || !components || !i18n) {
        return;
    }

    var el = element.createElement;
    var Fragment = element.Fragment;
    var __ = i18n.__;

    var InspectorControls = blockEditor && blockEditor.InspectorControls ? blockEditor.InspectorControls : null;
    var useBlockProps = blockEditor && blockEditor.useBlockProps ? blockEditor.useBlockProps : null;
    var PanelBody = components.PanelBody;
    var Placeholder = components.Placeholder;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;

    function toInt(value, fallback) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? fallback : parsed;
    }

    function getMenus() {
        return window.menumatic_blocks_data && Array.isArray(window.menumatic_blocks_data.menus)
            ? window.menumatic_blocks_data.menus
            : [];
    }

    function getBlockIcon() {
        return el(
            'svg',
            {
                width: 24,
                height: 24,
                viewBox: '0 0 24 24',
                fill: 'none',
                role: 'img',
                'aria-hidden': true,
                focusable: false,
                xmlns: 'http://www.w3.org/2000/svg'
            },
            el('path', {
                d: 'M4 2.5C4.41421 2.5 4.75 2.83579 4.75 3.25V8.5H5.75V3.25C5.75 2.83579 6.08579 2.5 6.5 2.5C6.91421 2.5 7.25 2.83579 7.25 3.25V8.5H8.25V3.25C8.25 2.83579 8.58579 2.5 9 2.5C9.41421 2.5 9.75 2.83579 9.75 3.25V9C9.75 10.3915 8.87833 11.5794 7.65234 12.0469V21C7.65234 21.4142 7.31656 21.75 6.90234 21.75H6.84766C6.43345 21.75 6.09766 21.4142 6.09766 21V12.0469C4.87167 11.5794 4 10.3915 4 9V3.25C4 2.83579 4.33579 2.5 4.75 2.5H4Z',
                fill: 'currentColor'
            }),
            el('path', {
                d: 'M14.8789 2.5C17.0192 2.5 18.75 4.23078 18.75 6.37109V21C18.75 21.4142 18.4142 21.75 18 21.75H17.25C16.8358 21.75 16.5 21.4142 16.5 21V13H14.5C14.0858 13 13.75 12.6642 13.75 12.25V6.37109C13.75 4.23078 12.7386 2.5 14.8789 2.5Z',
                fill: 'currentColor'
            })
        );
    }

    function getMenuOptions() {
        var options = [{ label: __('Choose a menu', 'menumatic'), value: '' }];

        getMenus().forEach(function (menu) {
            options.push({
                label: menu.name,
                value: menu.name
            });
        });

        return options;
    }

    function findMenuById(menuId) {
        return getMenus().find(function (menu) {
            return toInt(menu.id, 0) === toInt(menuId, 0);
        }) || null;
    }

    function renderPlaceholder(title, subtitle) {
        var wrapperProps = useBlockProps ? useBlockProps({
            className: 'menumatic-editor-placeholder'
        }) : {
            className: 'menumatic-editor-placeholder'
        };

        return el(
            'div',
            wrapperProps,
            el(
                Placeholder,
                {
                    icon: getBlockIcon(),
                    label: title,
                    instructions: subtitle
                }
            )
        );
    }

    function displayBlockEdit(props) {
        var attributes = props.attributes;
        var menuName = attributes.name || '';
        var legacyMenuId = toInt(attributes.id, 0);

        if (!menuName && legacyMenuId > 0) {
            var legacyMenu = findMenuById(legacyMenuId);
            if (legacyMenu) {
                props.setAttributes({ name: legacyMenu.name });
                menuName = legacyMenu.name;
            }
        }

        var controls = InspectorControls ? el(
            InspectorControls,
            {},
            el(
                PanelBody,
                { title: __('Menu Display Settings', 'menumatic'), initialOpen: true },
                el(SelectControl, {
                    label: __('Menu', 'menumatic'),
                    value: menuName,
                    options: getMenuOptions(),
                    onChange: function (value) {
                        props.setAttributes({ name: value || '', id: 0 });
                    },
                    help: __('Choose which menu to display.', 'menumatic')
                })
            )
        ) : null;

        return el(
            Fragment,
            {},
            controls,
            renderPlaceholder(
                __('MenuMatic Menu Display', 'menumatic'),
                menuName ? menuName : __('No menu selected', 'menumatic')
            )
        );
    }

    function managerBlockEdit(props) {
        var attributes = props.attributes;
        var controls = InspectorControls ? el(
            InspectorControls,
            {},
            el(
                PanelBody,
                { title: __('Menu Manager Settings', 'menumatic'), initialOpen: true },
                el(ToggleControl, {
                    label: __('Allow Upload', 'menumatic'),
                    checked: !!attributes.allowUpload,
                    onChange: function (value) {
                        props.setAttributes({ allowUpload: !!value });
                    }
                }),
                el(ToggleControl, {
                    label: __('Allow Delete', 'menumatic'),
                    checked: !!attributes.allowDelete,
                    onChange: function (value) {
                        props.setAttributes({ allowDelete: !!value });
                    }
                }),
                el(RangeControl, {
                    label: __('Max Menus', 'menumatic'),
                    value: toInt(attributes.maxMenus, 10),
                    min: 1,
                    max: 100,
                    onChange: function (value) {
                        props.setAttributes({ maxMenus: toInt(value, 10) });
                    }
                })
            )
        ) : null;

        return el(
            Fragment,
            {},
            controls,
            renderPlaceholder(
                __('MenuMatic Menu Manager', 'menumatic'),
                __('Frontend menu upload and editing interface', 'menumatic')
            )
        );
    }

    blocks.registerBlockType('menumatic/menu-display', {
        apiVersion: 2,
        title: __('MenuMatic Menu Display', 'menumatic'),
        description: __('Display a processed MenuMatic menu by name.', 'menumatic'),
        icon: {
            src: getBlockIcon()
        },
        category: 'widgets',
        attributes: {
            name: {
                type: 'string',
                default: ''
            },
            id: {
                type: 'number',
                default: 0
            }
        },
        edit: displayBlockEdit,
        save: function () {
            return null;
        }
    });

    blocks.registerBlockType('menumatic/menu-manager', {
        apiVersion: 2,
        title: __('MenuMatic Menu Manager', 'menumatic'),
        description: __('Frontend upload and management interface for MenuMatic.', 'menumatic'),
        icon: {
            src: getBlockIcon()
        },
        category: 'widgets',
        attributes: {
            allowUpload: {
                type: 'boolean',
                default: true
            },
            allowDelete: {
                type: 'boolean',
                default: true
            },
            maxMenus: {
                type: 'number',
                default: 10
            }
        },
        edit: managerBlockEdit,
        save: function () {
            return null;
        }
    });
})(
    window.wp && window.wp.blocks,
    window.wp && window.wp.element,
    (window.wp && (window.wp.blockEditor || window.wp.editor)) || null,
    window.wp && window.wp.components,
    window.wp && window.wp.i18n
);
