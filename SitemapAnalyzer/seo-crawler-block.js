// Gutenberg Block for SEO Crawler
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, RangeControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

registerBlockType('seo-crawler/crawler-widget', {
    title: 'SEO Crawler',
    icon: 'search',
    category: 'widgets',
    attributes: {
        height: {
            type: 'string',
            default: '600px'
        },
        defaultDelay: {
            type: 'number',
            default: 3
        },
        showHistory: {
            type: 'boolean',
            default: true
        }
    },

    edit: (props) => {
        const { attributes, setAttributes } = props;
        const blockProps = useBlockProps();

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title="SEO Crawler Settings">
                        <TextControl
                            label="Widget Height"
                            value={attributes.height}
                            onChange={(value) => setAttributes({ height: value })}
                        />
                        <RangeControl
                            label="Default Delay (seconds)"
                            value={attributes.defaultDelay}
                            onChange={(value) => setAttributes({ defaultDelay: value })}
                            min={1}
                            max={10}
                        />
                    </PanelBody>
                </InspectorControls>
                
                <div className="seo-crawler-block-preview">
                    <h3>SEO Crawler Widget</h3>
                    <p>Comprehensive SEO analysis tool will appear here on the frontend.</p>
                    <div style={{ 
                        height: attributes.height, 
                        border: '2px dashed #ccc', 
                        display: 'flex', 
                        alignItems: 'center', 
                        justifyContent: 'center' 
                    }}>
                        <span>SEO Crawler Interface ({attributes.height})</span>
                    </div>
                </div>
            </div>
        );
    },

    save: () => {
        return null; // Dynamic block rendered server-side
    }
});