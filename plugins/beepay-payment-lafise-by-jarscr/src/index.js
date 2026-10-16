import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';

const settings = window.wc.wcSettings.getSetting( 'beepaybylafise_data', {} );

const Label = () => {
    return decodeEntities( settings.title ) || 'Tarjeta de Crédito/Débito';
};

const Content = () => {
    return decodeEntities( settings.description || '' );
};

const beepaybylafisePaymentMethod = {
    name: 'beepaybylafise',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: 'Tarjeta de Crédito/Débito',
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod( beepaybylafisePaymentMethod );
