<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ empty($activeType) ? 'active' : '' }}" href="{{ url('admin/homestead/featured') }}">All Featured</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeType ?? null) === 'rooms' ? 'active' : '' }}" href="{{ url('admin/homestead/featured/rooms') }}">Featured Rooms</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeType ?? null) === 'houses' ? 'active' : '' }}" href="{{ url('admin/homestead/featured/houses') }}">Featured Houses</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeType ?? null) === 'characters' ? 'active' : '' }}" href="{{ url('admin/homestead/featured/characters') }}">Featured Characters</a>
    </li>
</ul>
