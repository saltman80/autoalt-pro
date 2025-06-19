import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { PluginSidebar } from '@wordpress/edit-post';
import { PanelBody, PanelRow, TextControl, Button, Spinner } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import apiFetch from '@wordpress/api-fetch';

function SidebarImageManager() {
    const blocks = useSelect( select => select( 'core/block-editor' ).getBlocks() );
    const imageBlocks = blocks.filter( block => block.name === 'core/image' );
    const { updateBlockAttributes } = useDispatch( 'core/block-editor' );
    const [loadingIds, setLoadingIds] = useState( [] );

    const handleAltChange = ( clientId, value ) => {
        updateBlockAttributes( clientId, { alt: value } );
    };

    const handleCaptionChange = ( clientId, value ) => {
        updateBlockAttributes( clientId, { caption: value } );
    };

    const generateAlt = async ( block ) => {
        const { clientId, attributes: { id } } = block;
        if ( ! id ) {
            return;
        }
        setLoadingIds( ids => [ ...ids, clientId ] );
        try {
            const response = await apiFetch( {
                path: `/autoalt/v1/generate?image_id=${ id }`,
                method: 'GET',
            } );
            if ( response && response.alt_text ) {
                updateBlockAttributes( clientId, { alt: response.alt_text } );
            }
        } catch ( error ) {
            console.error( 'Error generating alt text:', error );
        } finally {
            setLoadingIds( ids => ids.filter( i => i !== clientId ) );
        }
    };

    return (
        <PluginSidebar
            name="autoalt-image-manager"
            title="AutoAlt Pro"
            icon="format-image"
        >
            <PanelBody title="Image Alt & Captions" initialOpen={ true }>
                { imageBlocks.length === 0 && (
                    <PanelRow>No images in this post.</PanelRow>
                ) }
                { imageBlocks.map( block => {
                    const { clientId, attributes: { alt, caption, id } } = block;
                    const isLoading = loadingIds.includes( clientId );
                    return (
                        <PanelBody key={ clientId } title={ `Image ${ clientId }` } initialOpen={ false }>
                            <PanelRow>
                                <TextControl
                                    label="Alt Text"
                                    value={ alt || '' }
                                    onChange={ value => handleAltChange( clientId, value ) }
                                />
                            </PanelRow>
                            <PanelRow>
                                <Button
                                    isSecondary
                                    onClick={ () => generateAlt( block ) }
                                    disabled={ isLoading || ! id }
                                >
                                    { isLoading ? <Spinner /> : 'Generate Alt' }
                                </Button>
                            </PanelRow>
                            <PanelRow>
                                <TextControl
                                    label="Caption"
                                    value={ caption || '' }
                                    onChange={ value => handleCaptionChange( clientId, value ) }
                                />
                            </PanelRow>
                        </PanelBody>
                    );
                } ) }
            </PanelBody>
        </PluginSidebar>
    );
}

registerPlugin( 'autoalt-image-manager', {
    render: SidebarImageManager,
} );