<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/_traits.php';  // Generell funktions

// CLASS VideoPlayer
class VideoPlayer extends IPSModule
{
    use DebugHelper;

    // Min IPS Object ID
    private const IPS_MIN_ID = 10000;

    /**
     * Create.
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Video
        $this->RegisterPropertyString('MediaURL', 'https://');
        $this->RegisterPropertyBoolean('AutoPlay', false);
        $this->RegisterPropertyBoolean('Muted', false);
        $this->RegisterPropertyBoolean('Loop', false);
        $this->RegisterPropertyBoolean('DisablePictureInPicture', true);
        $this->RegisterPropertyBoolean('Controls', true);
        $this->RegisterPropertyBoolean('NoDownload', false);

        // Design
        $this->RegisterPropertyInteger('BackgroundColor', -1);
        $this->RegisterPropertyInteger('BackgroundImage', 1);

        // Advanced Settings
        $this->RegisterPropertyBoolean('AllowUpdate', false);

        // Set visualization type to 1, as we want to offer HTML
        /** @phpstan-ignore-next-line */
        $this->SetVisualizationType(1);
    }

    /**
     * Destroy.
     */
    public function Destroy(): void
    {
        parent::Destroy();
    }

    /**
     * Configuration Form.
     *
     * @return string configuration string.
     */
    public function GetConfigurationForm(): string
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Debug output
        //$this->LogDebug(__FUNCTION__, $form);
        return json_encode($form);
    }

    /**
     * Apply Configuration Changes.
     */
    public function ApplyChanges(): void
    {
        // Never delete this line!
        parent::ApplyChanges();

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        // Register references for variables
        $media = $this->ReadPropertyInteger('BackgroundImage');
        if ($media >= self::IPS_MIN_ID) {
            if (IPS_MediaExists($media)) {
                $this->RegisterReference($media);
            } else {
                $this->LogDebug(__FUNCTION__, 'Media does not exist - Variable: ' . $media);
                $this->SetStatus(104);
                return;
            }
        }

        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());

        // Set status
        $this->SetStatus(102);
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value): bool
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'SetMediaUrl':
                if (!empty($value)) {
                    if ($this->ReadPropertyBoolean('AllowUpdate')) {
                        IPS_SetProperty($this->InstanceID, 'MediaURL', $value);
                        if (IPS_HasChanges($this->InstanceID)) {
                            IPS_ApplyChanges($this->InstanceID);
                        }
                    }
                }
                break;
        }
        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
        // Always true
        return true;
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return string Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile(): string
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Check and add media object (background)
        $assets = '';
        $media = $this->ReadPropertyInteger('BackgroundImage');
        if ($media >= self::IPS_MIN_ID) {
            if (IPS_MediaExists($media)) {
                $image = IPS_GetMedia($media);
                if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                    $file = explode('.', $image['MediaFile']);
                    $content = $this->GetMediaType(end($file));
                    // Only continue if content has been set. Otherwise, the image is not a supported file type.
                    if ($content) {
                        // Append base64-encoded content of the image
                        $content .= IPS_GetMediaContent($media);
                    }
                    $assets = '<script>';
                    $assets .= 'window.assets = {};' . PHP_EOL;
                    $assets .= 'window.assets.bg = "' . $content . '";' . PHP_EOL;
                    $assets .= 'document.getElementById("player").poster = window.assets.bg;' . PHP_EOL;
                    $assets .= '</script>';
                }
            }
        }
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $assets . $initialHandling;
    }

    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        // dataset variable
        $result = [
            'color'     => $this->GetColorFormatted($this->ReadPropertyInteger('BackgroundColor')),
            'source'    => $this->ReadPropertyString('MediaURL'),
            'autoplay'  => $this->ReadPropertyBoolean('AutoPlay'),
            'muted'     => $this->ReadPropertyBoolean('Muted'),
            'loop'      => $this->ReadPropertyBoolean('Loop'),
            'pip'       => $this->ReadPropertyBoolean('DisablePictureInPicture'),
            'controls'  => $this->ReadPropertyBoolean('Controls'),
            'list'      => $this->ReadPropertyBoolean('NoDownload')
        ];
        $this->LogDebug(__FUNCTION__, $result);
        return json_encode($result);
    }

    /**
     * Get media type
     *
     * @param string $ext File extention
     *
     * @return string Media type data prefix
     */
    private function GetMediaType(string $ext): string
    {
        $type = '';
        switch ($ext) {
            case 'bmp':
                $type = 'data:image/bmp;base64,';
                break;
            case 'jpg':
            case 'jpeg':
                $type = 'data:image/jpeg;base64,';
                break;
            case 'gif':
                $type = 'data:image/gif;base64,';
                break;
            case 'png':
                $type = 'data:image/png;base64,';
                break;
            case 'ico':
                $type = 'data:image/x-icon;base64,';
                break;
            case 'webp':
                $type = 'data:image/webp;base64,';
                break;
        }
        return $type;
    }

    /**
     * Get HTML rgb formated color.
     *
     * @param int $color Color value or -1 for transparency
     * @return string HTML coded color or empty string
     */
    private function GetColorFormatted(int $color): string
    {
        if ($color != '-1') {
            return '#' . sprintf('%06X', $color);
        } else {
            return '';
        }
    }
}
