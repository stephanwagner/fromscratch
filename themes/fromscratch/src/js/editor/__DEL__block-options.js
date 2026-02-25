import { blockOptions } from "../../../config-block-options";

const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, ToggleControl, SelectControl } = wp.components;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;

// Add attributes to the block
blockOptions.forEach(block => {
  const blockSlug = getBlockSlug(block.name);

  wp.hooks.addFilter(
    'blocks.registerBlockType',
    'custom-block-options/block-' + blockSlug,
    (settings, name) => {
      if (name === block.name) {
        block.options.forEach(option => {
          settings.attributes = {
            ...settings.attributes,
            [option.attributeName]: {
              type: option.type,
              default: option.default,
            },
          };
        });
      }
      return settings;
    }
  )
});

// Add custom control
const addControl = createHigherOrderComponent((BlockEdit) => {
  return (props) => {
    const { attributes, setAttributes, isSelected } = props;

    // Find the block configuration based on the block name
    const blockConfig = blockOptions.find(block => block.name === props.name);

    if (blockConfig) {
      return (
        <Fragment>
          <BlockEdit {...props} />
          {isSelected && (
            <InspectorControls>
              <PanelBody title="Block Einstellungen">
                {blockConfig.options.map(option => {
                  if (option.type === 'boolean') {
                    return (
                      <ToggleControl
                        key={option.attributeName}
                        label={option.label}
                        checked={attributes[option.attributeName]}
                        onChange={(newValue) => setAttributes({ [option.attributeName]: newValue })}
                      />
                    );
                  } else if (option.type === 'select') {
                    return (
                      <SelectControl
                        key={option.attributeName}
                        label={option.label}
                        value={attributes[option.attributeName]}
                        options={option.options}
                        onChange={(newValue) => setAttributes({ [option.attributeName]: newValue })}
                      />
                    );
                  }
                  return null;
                })}
              </PanelBody>
            </InspectorControls>
          )}
        </Fragment>
      );
    }

    return <BlockEdit {...props} />;
  };
}, 'addControl');

// Apply heading classes in the editor's preview
const applyClasses = (BlockListBlock) => {
  return (props) => {
    const { block, attributes } = props;

    // Find the block configuration based on the block name
    const blockConfig = blockOptions.find(block => block.name === props.name);

    if (blockConfig && attributes) {
      let classNames = props.className || '';

      // Loop through options and apply the corresponding class if conditions are met
      blockConfig.options.forEach(option => {
        if (option.type === 'boolean' && attributes[option.attributeName]) {
          classNames += ` ${option.className}`;
        } else if (option.type === 'select' && attributes[option.attributeName]) {
          classNames += ` ${attributes[option.attributeName]}`;
        }
      });

      const blockProps = useBlockProps({
        className: classNames.trim(),
      });

      return <BlockListBlock {...props} {...blockProps} />;
    }

    return <BlockListBlock {...props} />;
  };
};

// Apply classes when the block is saved
const saveClasses = (extraProps, blockType, attributes) => {
  // Find the block configuration based on the blockType name
  const blockConfig = blockOptions.find(block => block.name === blockType.name);

  if (blockConfig && attributes) {
    let classNames = extraProps.className || '';

    // Loop through options and apply the corresponding class if conditions are met
    blockConfig.options.forEach(option => {
      if (option.type === 'boolean' && attributes[option.attributeName]) {
        classNames += ` ${option.className}`;
      } else if (option.type === 'select' && attributes[option.attributeName]) {
        classNames += ` ${attributes[option.attributeName]}`;
      }
    });

    extraProps.className = classNames.trim();
  }

  return extraProps;
};

// Hooks to add control and class
wp.hooks.addFilter('editor.BlockEdit', 'custom-block-options/add-control', addControl);
wp.hooks.addFilter('editor.BlockListBlock', 'custom-block-options/apply-classes', applyClasses);
wp.hooks.addFilter('blocks.getSaveContent.extraProps', 'custom-block-options/save-classes', saveClasses);

/**
 * Get the slug of a block name
 */
function getBlockSlug(blockName) {
  return blockName.replace('/', '-');
}
