<div class="navbar-header">
  <div class="row align-items-center justify-content-between">
    <div class="col-auto">
      <div class="d-flex flex-wrap align-items-center gap-4">
        <button type="button" class="sidebar-toggle btn btn-light">
          <iconify-icon icon="heroicons:bars-3-solid" class="icon text-2xl non-active"></iconify-icon>
          <iconify-icon icon="iconoir:arrow-right" class="icon text-2xl active" style="display:none;"></iconify-icon>
        </button>
        <button type="button" class="sidebar-mobile-toggle btn btn-light">
          <iconify-icon icon="heroicons:bars-3-solid" class="icon"></iconify-icon>
        </button>
        <form class="navbar-search">
          <input type="text" name="search" placeholder="Search">
          <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
        </form>
      </div>
    </div>
    <div class="col-auto">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <button type="button" id="themeToggle" data-theme-toggle class="btn btn-light w-40-px h-40-px rounded-circle d-flex justify-content-center align-items-center">
          <iconify-icon icon="tabler:sun" class="icon"></iconify-icon>
        </button>

        <a href="logout.php" title="Logout" class="btn btn-light w-40-px h-40-px rounded-circle d-flex justify-content-center align-items-center text-danger-600">
          <iconify-icon icon="lucide:log-out" class="icon text-xl"></iconify-icon>
        </a>
  
        <div class="dropdown">
          <button class="btn btn-light d-flex justify-content-center align-items-center rounded-circle dropdown-toggle-nocaret" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="assets/images/mos_icon.png" alt="image" class="w-40-px h-40-px object-fit-cover rounded-circle">
          </button>
          <ul class="dropdown-menu dropdown-menu-end border shadow-sm" aria-labelledby="profileDropdown">
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="view-profile.php">
                <iconify-icon icon="flowbite:user-outline" class="icon text-xl"></iconify-icon>
                <span>Your Profile</span>
              </a>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <li>
              <a class="dropdown-item text-danger d-flex align-items-center gap-2 py-2" href="logout.php">
                <iconify-icon icon="lucide:log-out" class="icon text-xl"></iconify-icon>
                <span>Logout</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
