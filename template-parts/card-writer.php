<section class="m3-writer-card">
    <div class="m3-writer-card__header">
        <h3 class="m3-writer-card__title">WRITER INFO</h3>
    </div>
    <div class="m3-writer-card__body">
        <div class="m3-writer-card__avatar">
            <?php echo get_avatar(get_the_author_meta('ID'), 100); ?>
        </div>
        <div class="m3-writer-card__info">
            <h4 class="m3-writer-card__name"><?php the_author(); ?></h4>
            <div class="m3-writer-card__bio">
                <?php the_author_meta('description'); ?>
            </div>
        </div>
    </div>
</section>