<span class="clipboard-button text-muted text-decoration-none"
   data-toggle="tooltip" title=""
   data-original-title="Copy to clipboard"
    id="{{$id}}"
   data-clipboard-text="{{$text}}">
    &nbsp;<i class="clipboard-button-icon fas fa-copy"></i>
</span>

@push('script')
    <script type="text/javascript">
        $('#{{$id}}').on('click', function(event) {
            let clipboard = new ClipboardJS("#{{$id}}");
            let clipboardElement = $("#{{$id}}");
            clipboard.on('success', function(e) {
                clipboardElement.html('&nbsp;<i class="clipboard-button-icon fas fa-check-circle"></i> Copied')
                setTimeout(function() {
                    clipboardElement.html('&nbsp;<i class="clipboard-button-icon fas fa-copy"></i>')
                }, 1000);
            });
        });
    </script>
@endpush
