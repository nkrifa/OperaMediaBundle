<?php

namespace Opera\MediaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Opera\MediaBundle\Repository\FolderRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Opera\MediaBundle\MediaManager\MediaManager;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Opera\MediaBundle\Entity\Folder;
use Opera\MediaBundle\Entity\Media;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends Controller
{
    /**
     * @Route("/media/folder/form/{id}", defaults={ "id": null }, name="opera_admin_media_folder_form")
     * @Template
     */
    public function formFolder(MediaManager $mediaManager, Folder $folder = null, Request $request)
    {
        $form = $mediaManager->prepareFolderForm($folder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $folder = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($folder);
            $entityManager->flush();
    
            return $this->redirectToRoute('opera_admin_media_list', []);
        }

        return [
            'folder' => $folder,
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/media/media/form/{id}", defaults={ "id": null }, name="opera_admin_media_media_form")
     * @Template
     */
    public function formMedia(MediaManager $mediaManager, Request $request, ?Media $media = null)
    {
        $form = $mediaManager->prepareMediaForm($media);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->files && $request->files->get('media') && isset($request->files->get('media')['path'])) {
                $media = $form->getData();
                $file = $request->files->get('media')['path'];
                $mediaManager->uploadAndPrepareMediaForSave($file, $media);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($media);
            $entityManager->flush();
    
            return $this->redirectToRoute('opera_admin_media_list', []);
        }

        return [
            'media' => $media,
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/media/{source_name}/{folder_id}", defaults={ "folder_id": null, "source_name": null }, name="opera_admin_media_list")
     * @Entity("folder", expr="folder_id ? repository.findOneBySourceAndId(source_name, folder_id) : null")
     * @Template
     */
    public function view(?string $source_name = null, ?Folder $folder = null, MediaManager $mediaManager)
    {
        $sources = $mediaManager->getSources();
        $selectedSource = $source_name ? $mediaManager->getSource($source_name) : array_values($sources)[0];
        $items = $selectedSource->list($folder);

        // @Todo remove this and how to get in twig the image directly with the selectedSource ?
        $images = [];
        foreach ($items as $item) {
            if ($item instanceof Media) {
                $images[$item->getPath()] = $selectedSource->getBase64($item);
            }
        }
        // @Todo end

        return [
            'sources' => $sources,
            'selected_folder' => $folder,
            'selected_source' => $selectedSource,
            'items' => $items,
            'images' => $images,
        ];
    }


    /**
     * @Route("/media/folder/{id}/delete", name="opera_admin_media_delete_folder")
     */
    public function deleteFolder(Folder $folder)
    {
        $sourceName = $folder->getSource();
        $parentFolderId = $folder->getParent() ? $folder->getParent()->getId() : null;
    
        $em = $this->getDoctrine()->getManager();
        $em->remove($folder);
        $em->flush();

        return $this->redirectToRoute('opera_admin_media_list', [
            'source_name' => $sourceName,
            'folder_id' => $parentFolderId,
        ]);
    }

    /**
     * @Route("/media/media/{id}/delete", name="opera_admin_media_delete_media")
     */
    public function deleteMedia(MediaManager $mediaManager, Media $media)
    {
        $sourceName = $media->getSource();
        $parentFolderId = $media->getFolder() ? $media->getFolder()->getId() : null;
        $source = $this->mediaManager->getSource($media->getSource());
        $source->delete($media->getPath());

        $em = $this->getDoctrine()->getManager();
        $em->remove($media);
        $em->flush();

        return $this->redirectToRoute('opera_admin_media_list', [
            'source_name' => $sourceName,
            'folder_id' => $parentFolderId,
        ]);
    }

}
