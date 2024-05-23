import {
  Button,
  FormControl,
  FormLabel,
  Input,
  Textarea,
  VStack,
  Text,
  HStack,
} from '@calvient/decal';
import Layout from '../../Components/Layout.tsx';
import {router, useForm} from '@inertiajs/react';
import {Report} from '../../Types/Report.ts';
import ConfirmableBtn from '../../Components/ConfirmableBtn.tsx';

interface Props {
  report: Report;
}

const Edit = ({report}: Props) => {
  const {data, setData, put, processing, errors} = useForm(report);

  function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    put(`/arbol/reports/${report.id}`);
  }

  return (
    <form onSubmit={submit}>
      <VStack spacing={4}>
        <FormControl isRequired>
          <FormLabel>Report Name</FormLabel>
          <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
          {errors.name && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.name}
            </Text>
          )}
        </FormControl>

        <FormControl>
          <FormLabel>Report Description</FormLabel>
          <Textarea
            value={data.description}
            onChange={(e) => setData('description', e.target.value)}
          />
          {errors.description && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.description}
            </Text>
          )}
        </FormControl>

        <HStack w={'full'} justifyContent={'space-between'}>
          <ConfirmableBtn
            variant={'ghost'}
            colorScheme={'red'}
            onConfirm={() => router.delete(`/arbol/reports/${report.id}`)}
          >
            Delete
          </ConfirmableBtn>
          <Button type={'submit'} isLoading={processing} colorScheme={'blue'}>
            Edit Report
          </Button>
        </HStack>
      </VStack>
    </form>
  );
};

Edit.layout = (page: React.ReactElement) => <Layout children={page} />;

export default Edit;
