</div>
</div> <!-- Close page-content -->

    <!-- Footer -->
    <footer class="arm-footer">
      <div class="grid-container">
        <div class="grid-x grid-margin-x">
          <div class="small-12 medium-6 cell">
            <p class="arm-footer-title">Arizona Railway Museum</p>
            <p>
              330 E. Ryan Road<br>
              Chandler, AZ 85286<br>
              (480) 821-1108
            </p>
          </div>
          <div class="small-12 medium-6 cell text-right medium-text-right small-text-left">
            <p>Thank you for visiting the Arizona Railway Museum!<br>
            <p class="arm-last-update">
              Last Update: December 21, 2025<br>
            </p>
            &copy; 2001–<?php echo date('Y'); ?> Arizona Railway Museum. All rights reserved.</p>
          </div>
        </div>
      </div>
    </footer>

<script src="/js/vendor/jquery.js"></script>
<script src="/js/vendor/what-input.js"></script>
<script src="/js/vendor/foundation.js"></script>
<script>
  $(document).foundation();

  // Mobile dropdown toggle behavior
  (function() {
    var isMobile = function() {
      return window.matchMedia('(max-width: 39.9375em)').matches;
    };

    // Destroy Foundation dropdown on mobile, reinit on desktop
    var $dropdownMenu = $('#main-menu .top-bar-right > .dropdown.menu');
    var lastMobileState = null;

    function handleResize() {
      var mobile = isMobile();
      if (mobile === lastMobileState) return;
      lastMobileState = mobile;

      if (mobile) {
        // Destroy Foundation dropdown on mobile
        var instance = $dropdownMenu.data('zfPlugin');
        if (instance) {
          instance.destroy();
        }
      } else {
        // Reinitialize on desktop
        if (!$dropdownMenu.data('zfPlugin')) {
          new Foundation.DropdownMenu($dropdownMenu);
        }
      }
    }

    // Run on load and resize
    handleResize();
    $(window).on('resize', handleResize);

    // Mark items that have submenus
    $('#main-menu .dropdown.menu > li').each(function() {
      if ($(this).find('.menu.vertical').length) {
        $(this).addClass('has-submenu');
      }
    });

    // Toggle dropdown on click (mobile only)
    $(document).on('click', '#main-menu .dropdown.menu > li > a', function(e) {
      if (!isMobile()) return;

      var $submenu = $(this).siblings('.menu.vertical');
      if ($submenu.length === 0) return; // No submenu, let link work normally

      e.preventDefault();

      var $parent = $(this).parent();
      var isOpen = $parent.hasClass('is-open');

      // Close all other open dropdowns
      $('#main-menu .dropdown.menu > li').removeClass('is-open')
        .find('.menu.vertical').hide();

      // Toggle this one
      if (!isOpen) {
        $parent.addClass('is-open');
        $submenu.show();
      }
    });

    // Close dropdowns when clicking outside (mobile only)
    $(document).on('click', function(e) {
      if (!isMobile()) return;
      if (!$(e.target).closest('#main-menu').length) {
        $('#main-menu .dropdown.menu > li').removeClass('is-open')
          .find('.menu.vertical').hide();
      }
    });
  })();
</script>
  </body>
</html>
