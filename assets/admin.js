jQuery( function ( $ ) {
    var frame;

    $( '.blackout-color' ).wpColorPicker();

    $( '#blackout_mode_logo_button' ).on( 'click', function ( e ) {
        e.preventDefault();

        if ( frame ) {
            frame.open();
            return;
        }

        frame = wp.media( {
            title: blackoutMode.selectLogo,
            button: { text: blackoutMode.useLogo },
            library: { type: 'image' },
            multiple: false
        } );

        frame.on( 'select', function () {
            var attachment = frame.state().get( 'selection' ).first().toJSON();
            $( '#blackout_mode_logo' ).val( attachment.url );
            $( '#blackout_mode_logo_preview' ).empty().append( $( '<img>', { src: attachment.url, alt: '' } ) );
        } );

        frame.open();
    } );

    $( '#blackout_mode_clear_logo_button' ).on( 'click', function () {
        $( '#blackout_mode_logo' ).val( '' );
        $( '#blackout_mode_logo_preview' ).empty();
    } );
} );
